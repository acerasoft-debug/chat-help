<?php
/**
 * ChatHelp — dump-profile-frontend (SADECE OKUR) — Konto ADIM 2 (frontend) icin:
 *  (1) ch_profiles nasil okunuyor/yaziliyor (localStorage anahtari, kaydet butonu,
 *      profil degistirici), (2) login sonrasi 'me' cagrisinin TAM baglami (token
 *      saklandiktan sonra ne calisiyor), (3) login 403 (email dogrulanmamis) hata
 *      gosterimi -> resend butonu icin dogru yer, (4) doMe/login sonrasi UI'a nasil
 *      yansitiliyor (renderKontoPlanSelect vb). HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-profile-frontend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-profile-frontend — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

function occ($label,$hay,$needle,$pre,$len,$max=6){
  echo "── $label ('$needle') [toplam ".substr_count($hay,$needle)."] ──\n";
  $off=0;$n=0;
  while(($p=strpos($hay,$needle,$off))!==false && $n<$max){
    $seg=substr($hay,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if(!$n) echo "  (yok)\n";
  echo "\n";
}
function bodyOf($s,$decl,$cap=1500){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)\n";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,160)."\n";
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)\n":"\n");
}

echo "════ [1] ch_profiles okunma/yazilma ════\n";
occ('ch_profiles',$src,'ch_profiles',30,110,10);

echo "════ [2] token saklandiktan sonra (login basari) ════\n";
occ('login basari sonrasi',$src,"localStorage.setItem('ch_token'",60,240,3);
occ('token sonrasi me cagrisi',$src,"ch_token', d.token",40,260,3);

echo "════ [3] login HATA gosterimi (403/email dogrulanmadi) ════\n";
occ('login catch/error',$src,'error.message',40,180,6);
occ('email dogrulanmadi metni',$src,'bestätigen',40,220,6);
occ('login fonksiyonu adi (fetch action=login)',$src,'action=login',60,200,4);

echo "════ [4] doMe/renderKonto sonrasi UI ════\n";
occ('renderKontoPlanSelect',$src,'renderKontoPlanSelect',30,140,3);
occ('doMe cagrisi frontend',$src,'action=me',40,200,4);

echo "════ [5] profil kaydet/degistir butonlari ════\n";
occ('profil ekle/kaydet',$src,'ch_profiles.push',30,140,4);
occ('ch_prof_active',$src,'ch_prof_active',30,130,6);
occ('saveProfiles fn (varsa)',$src,'function saveProfiles',0,400,2);
occ('profil switch UI',$src,'prof-switch',30,120,4);

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
