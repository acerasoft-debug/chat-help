<?php
/** ChatHelp — dump-account (SADECE OKUR) — KRITIK: yeni musteriler kayit olunca
 *  hesapta "Acerasoft" (kullanicinin kendi hesabi) cikiyor + plan Elite donuyor.
 *  Bu server-side auth/plan sorunu. Kanit toplama:
 *  [1] auth.php TUM fonksiyon adlari + routing (hangi action ne yapiyor)
 *  [2] register (kayit) govdesi — yeni kullanici nasil olusturuluyor, default plan ne
 *  [3] login govdesi — dogru kullaniciyi mi donduruyor
 *  [4] "me"/profil dondurme govdesi — token'dan hangi user cekiliyor
 *  [5] HARD-CODE tehlike: auth.php + index.php icinde 'acerasoft','elite','admin',
 *      sabit token/email/plan var mi (herkesi ayni hesaba dusuren bir sey)
 *  [6] users tablosu SELECT/INSERT sorgulari (WHERE kosulu dogru mu — herkese
 *      ayni satiri donduren bir sorgu var mi, ornegin LIMIT 1 WHERE'siz)
 *  [7] index.php: ch_user/ch_token/ch_profile localStorage'a NE yaziliyor
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$auth=(string)@file_get_contents("$D/auth.php");
echo "auth.php: ".(strlen($auth)?strlen($auth)." bayt":"YOK")." | index.php: ".strlen($idx)." bayt\n";

function bodyOf($s,$name,$cap=1800){
  $p=strpos($s,$name); if($p===false) return "(YOK: $name)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,200);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  $body=substr($s,$p,min($i-$p,$cap));
  return $body.(($i-$p)>$cap?"\n…(kirpildi)":"");
}
function occ($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──── $label ('$needle') ────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "\n════ [1] auth.php fonksiyon adlari + routing ════\n";
if(preg_match_all('/function\s+([A-Za-z_]\w*)\s*\(/',$auth,$m)){
    echo "  fonksiyonlar: ".implode(', ',$m[1])."\n";
}
occ($auth,'=> do',-30,80,'routing (=> doX)',20);
occ($auth,"action",-10,70,'action degiskeni',10);

echo "\n════ [2] register/signup govdesi ════\n";
foreach(['doRegister','doSignup','function register','doRegistrieren','doCreateUser','doRegist'] as $fn){
    if(strpos($auth,$fn)!==false){ echo "\n---- $fn ----\n".bodyOf($auth,$fn,2200)."\n"; break; }
}

echo "\n════ [3] login govdesi ════\n";
foreach(['doLogin','function login','doSignin','doAnmeld'] as $fn){
    if(strpos($auth,$fn)!==false){ echo "\n---- $fn ----\n".bodyOf($auth,$fn,2000)."\n"; break; }
}

echo "\n════ [4] me/profil/verify govdesi ════\n";
foreach(['doMe','doProfile','doVerify','doSession','doAccount','doWhoami','doGetUser'] as $fn){
    if(strpos($auth,$fn)!==false){ echo "\n---- $fn ----\n".bodyOf($auth,$fn,1800)."\n"; }
}

echo "\n════ [5] HARD-CODE tehlike taramasi ════\n";
foreach(['auth.php'=>$auth,'index.php'=>$idx] as $fn=>$s){
    echo "\n-- $fn --\n";
    foreach(['acerasoft','Acerasoft','ACERASOFT',"'elite'","\"elite\"",'admin','Serdar'] as $needle){
        $c=substr_count($s,$needle);
        if($c>0){ echo "  '$needle': $c kez"; $p=strpos($s,$needle); echo "  ilk@$p: ".str_replace("\n"," ",substr($s,max(0,$p-40),110))."\n"; }
    }
}

echo "\n════ [6] users tablosu sorgulari ════\n";
occ($auth,'FROM users',-40,220,'SELECT ... FROM users',8);
occ($auth,'INSERT INTO users',-10,260,'INSERT INTO users',4);
occ($auth,'UPDATE users',-10,200,'UPDATE users',4);
occ($auth,'plan',-30,90,'plan gecen satirlar (auth)',12);

echo "\n════ [7] index.php: ch_user/ch_token/ch_profile yazimlari ════\n";
occ($idx,"setItem('ch_user'",-40,200,'ch_user yazimi',6);
occ($idx,'setItem("ch_user"',-40,200,'ch_user yazimi (cift tirnak)',6);
occ($idx,"setItem('ch_token'",-40,180,'ch_token yazimi',6);
occ($idx,'ch_prof_v3',-40,140,'ch_prof_v3',8);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
