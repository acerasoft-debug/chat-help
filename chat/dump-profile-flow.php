<?php
/** ChatHelp — dump-profile-flow: profil KAYIT yolu vs Konto'daki GOSTERIM yolu (neden "—" gorunuyor).
 *  SADECE OKUR. SIL: rm dump-profile-flow.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,60)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=6){echo "\n──── $lbl (ilk $max) ────\n";$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ 1) PROFIL KAYIT YOLU (editor formu + kaydet) ############\n";
R($idx,'Profil gespeichert',-900,1200,'profil kaydet handler (P.f* atamalari + localStorage anahtari)');
RALL($idx,'FK',-60,140,'FK sabiti kullanimlari (ch_prof_v3)',6);
R($idx,"localStorage.setItem(FK",-200,400,'FK ile kayit');
R($idx,"localStorage.getItem(FK",-200,400,'FK ile okuma (P yukleme)');

echo "\n\n############ 2) KONTO'DAKI PROFIL GOSTERIMI (neden \"—\") ############\n";
R($idx,'function chFillKontoUser',0,1400,'chFillKontoUser TAM govde');
R($idx,'chFillKontoUser',-300,500,'chFillKontoUser cagri baglami');
R($idx,'function loadKonto',0,1200,'loadKonto TAM govde');
RALL($idx,'ku-',-80,180,'ku-* eleman IDleri (Konto alanlari)',10);
R($idx,'Eliminar',-300,400,'profil satirlari HTML (Eliminar cevirili buton civari)');
R($idx,'Profil löschen',-400,600,'Profil bolumu HTML (almanca orijinal)');
RALL($idx,'—',-80,160,'"—" (tire) placeholder gecen yerler',6);

echo "\n\n############ 3) EDIT FORMU ALANLARI (f.id listesi) ############\n";
R($idx,'value="${P[f.id]',-700,1000,'editor alan tanimlari (id/ph listesi)');

echo "\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-profile-flow.php ===\n";
