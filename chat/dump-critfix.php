<?php
/** ChatHelp — dump-critfix (SADECE OKUR) — iki kritik sorunun kesin kok nedeni:
 *  A) FOTO: doPhoto() 'claude-sonnet-4-6' (GECERSIZ model) kullaniyor -> foto
 *     bos donuyor. callClaude() calisan modeli kullaniyor. O calisan modeli
 *     almam lazim ki doPhoto'ya koyayim.
 *  B) HESAP: yeni musteriye "Acerasoft"+Elite gorunuyor. doMe/doLogin dogru
 *     user donduruyor mu, Konto profil doldurma nereden veri cekiyor,
 *     ust bar auth-label nereden geliyor. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");
$auth=(string)@file_get_contents("$D/auth.php");

function fnBody($s,$decl,$cap=2000){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,200);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)":"");
}

echo "════ [A1] callClaude() TAM govde (calisan model burada) ════\n";
echo fnBody($api,'function callClaude(',1800)."\n";

echo "\n════ [A2] callClaudeVision (varsa) ════\n";
echo fnBody($api,'function callClaudeVision(',1400)."\n";

echo "\n════ [A3] model= satirlari (api.php) ════\n";
$off=0;$n=0;
while(($q=strpos($api,"'model'",$off))!==false && $n<12){
    echo "[@$q] ".str_replace("\n"," ",substr($api,max(0,$q-10),110))."\n"; $off=$q+6;$n++;
}
$off=0;$n=0;
while(($q=strpos($api,'$model',$off))!==false && $n<8){
    echo "[\$model @$q] ".str_replace("\n"," ",substr($api,max(0,$q-40),120))."\n"; $off=$q+6;$n++;
}

echo "\n════ [B1] doLogin() TAM govde ════\n";
echo fnBody($auth,'function doLogin(',1900)."\n";

echo "\n════ [B2] doMe() TAM govde ════\n";
echo fnBody($auth,'function doMe(',1700)."\n";

echo "\n════ [B3] Konto profil doldurma (chFillKontoUser / benzeri) ════\n";
$b3=fnBody($idx,'function chFillKontoUser(',1600);
if(strpos($b3,'YOK')!==false){
    foreach(['function fillKonto','function renderKonto','function updateAuthUI','function updateAuth'] as $fn){
        $bb=fnBody($idx,$fn,1400);
        if(strpos($bb,'YOK')===false){ echo "---- $fn ----\n".$bb."\n"; }
    }
} else echo $b3."\n";

echo "\n════ [B4] ust bar hesap adi (tb-auth-label) ════\n";
$off=0;$n=0;
while(($q=strpos($idx,'tb-auth-label',$off))!==false && $n<6){
    echo "[@$q] ".str_replace("\n"," ⏎ ",substr($idx,max(0,$q-140),260))."\n\n";
    $off=$q+12;$n++;
}

echo "\n════ [B5] chUser()/loggedIn() — client kimlik kaynagi ════\n";
echo fnBody($idx,'function chUser(',500)."\n";
$off=0;$n=0;
while(($q=strpos($idx,"getItem('ch_user'",$off))!==false && $n<6){
    echo "[@$q] ".str_replace("\n"," ",substr($idx,max(0,$q-50),150))."\n"; $off=$q+10;$n++;
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
