<?php
/** ChatHelp — dump-freegate (SADECE OKUR) — 1-free-dilekce + IP/email kotasi icin:
 *  Free PDF hem e-posta hem IP basina 1 ile sinirlanmali (server-side).
 *  Mevcut altyapiyi gormeliyim:
 *  [1] client kredi sistemi (credits/useCredit/addCredit) — tam govde
 *  [2] chDlGate — PDF kapisi (free kredi eklenecek nokta)
 *  [3] api.php: kota/free ile ilgili action + doGenerate server-side kontrol
 *  [4] users tablosu ek sutunlar (free_used/credits) + user_documents kullanim
 *  [5] IP alma (api.php visitor_ip/REMOTE_ADDR) — IP-basina kota icin
 *  [6] stripe-checkout single-doc akisi (free bitince yonlendirme)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");
$auth=(string)@file_get_contents("$D/auth.php");

function fnBody($s,$decl,$cap=900){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…":"");
}
function occ($src,$needle,$pre,$len,$label,$max=5){
    echo "\n──── $label ('$needle') ────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] client kredi sistemi ════\n";
foreach(['function credits','function useCredit','function addCredit','function loggedPlan'] as $fn){
    echo "\n---- $fn ----\n".fnBody($idx,$fn,700)."\n";
}
occ($idx,'ch_credit',-40,120,'ch_credit localStorage anahtari',8);

echo "\n════ [2] chDlGate (PDF kapisi) TAM ════\n";
echo fnBody($idx,'window.chDlGate=function(',1400)."\n";

echo "\n════ [3] api.php kota/free action + doGenerate ════\n";
occ($api,'=> do',-4,60,'api routing (action->handler)',24);
occ($api,'free',-30,110,'free gecen yerler (api)',10);
occ($api,'credit',-30,110,'credit gecen yerler (api)',8);
echo "\n---- doGenerate bas (server plan/kota kontrolu?) ----\n";
echo fnBody($api,'function doGenerate(',1400)."\n";

echo "\n════ [4] tablo sutunlari (free/credit/usage) ════\n";
occ($auth,'free_used',-20,100,'free_used sutunu (auth)',4);
occ($api,'user_documents',-20,140,'user_documents kullanim (api)',5);
occ($auth,'user_plans',-20,120,'user_plans (auth)',4);

echo "\n════ [5] IP alma (api.php) ════\n";
occ($api,'REMOTE_ADDR',-40,120,'REMOTE_ADDR',5);
occ($api,'HTTP_X_FORWARDED_FOR',-30,110,'X-Forwarded-For',4);
occ($api,'function visitor_ip',0,400,'visitor_ip (varsa)',1);

echo "\n════ [6] stripe single-doc (free bitince) ════\n";
occ($idx,'"devdoc"',-60,120,'devdoc tek-belge',4);
occ($idx,'stripe-checkout',-40,120,'stripe-checkout cagrisi',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
