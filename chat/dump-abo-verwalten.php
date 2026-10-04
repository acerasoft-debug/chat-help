<?php
/**
 * ChatHelp — dump-abo-verwalten (SADECE OKUR) — "Zahlungsmethode / Abo
 *  verwalten" segmenti calismiyor. Bul: (1) segmentin TAM govdesi (buton,
 *  onclick, hangi fonksiyonu cagiriyor), (2) o fonksiyonun TAM govdesi
 *  (billing_portal cagrisi + hata gosterimi), (3) segment Konto'da GERCEKTEN
 *  render ediliyor mu (DOM'a ekleniyor mu) yoksa CSS ile mi gizli.
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-abo-verwalten.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-abo-verwalten — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

function bodyOf($s,$decl,$cap=2500){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)\n";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,180)."\n";
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)\n":"\n");
}

/* [1] 'Zahlungsmethode / Abo verwalten' cevresindeki TAM IIFE */
echo "[1] 'Zahlungsmethode / Abo verwalten' cevresindeki blok (±200 once, 3000 sonra):\n";
$p=strpos($src,'Zahlungsmethode / Abo verwalten');
if($p!==false){
  $s0=max(0,$p-250);
  echo substr($src,$s0,3200)."\n";
} else echo "(BULUNAMADI — metin degismis olabilir)\n";

/* [2] billing_portal cagiran fonksiyon TAM govde */
echo "\n[2] billing_portal cagrisi baglamlari:\n";
$off=0;$n=0;
while(($p=strpos($src,'billing_portal',$off))!==false && $n<6){
  $seg=substr($src,max(0,$p-200),350); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  [@$p] …".$seg."…\n"; $off=$p+14;$n++;
}

/* [3] Konto panelinde bu segmentin ekleniyor mu (injectKontoNote, addKonto* vb) */
echo "\n[3] Konto'ya ekleyen fonksiyon adi + cagrildigi yer:\n";
$off=0;$n=0;
while(($p=strpos($src,'AboVerwalten',$off))!==false && $n<10){
  $seg=substr($src,max(0,$p-60),140); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+12;$n++;
}
if($n===0) echo "  (AboVerwalten adinda fonksiyon yok — farkli isimli olabilir, [1] ciktisina bak)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
