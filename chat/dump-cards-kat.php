<?php
/**
 * ChatHelp — dump-cards-kat (SADECE OKUR) — 2 sikayet teshisi:
 *  [A] "ana sayfada kartlar kaybolup geliyor" — hangi dongu/siniflar .wcat/
 *      .wcat-grid gorunurlugunu degistiriyor (ch-clean, killWelcome, stab
 *      marker durumlari, display manipulasyonlari)
 *  [B] "Verträge kündigen kartinda sozlesmeler cikiyor" — 'vertraege'
 *      kategorisine hangi girdiler kayitli / kim push ediyor (soru-cevap
 *      kundigung sablonlari yerine Vertrag Studio sozlesmeleri mi sizmis?)
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-cards-kat.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-cards-kat — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=110,$len=380,$max=6){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n"; echo "\n";
}

echo "───── [A] Kart titremesi ─────\n\n";
echo "[A0] Stabilite marker'lari:\n";
foreach(['CH_CARDS_CLEAN','CH_STAB_FINAL2','CH_STAB_FINAL3','CH_STAB_FINAL4','CH_I18N_TRUCE','CH_CLEANFIX','CH_UI_STABILITY'] as $m)
  echo "  ".str_pad($m,16).": ".substr_count($src,$m)." kez\n";
echo "\n";
occ('A1',$src,"classList.add('ch-clean'",80,260,6);
occ('A2',$src,"classList.remove('ch-clean'",80,260,6);
occ('A3',$src,"classList.toggle('ch-clean'",80,260,6);
occ('A4',$src,'killWelcome',80,420,4);
occ('A5',$src,"wcat-grid').style",100,300,6);
occ('A6',$src,'.wcat-grid{display',60,200,8);
occ('A7',$src,".wlc-quick",60,200,8);

echo "───── [B] 'vertraege' kategorisi kirliligi ─────\n\n";
occ('B1',$src,"cat:'vertraege'",120,260,10);
occ('B2',$src,'cat:"vertraege"',120,260,10);
occ('B3',$src,"CAT_DOCS['vertraege']",120,400,6);
occ('B4',$src,"'vertraege':",100,700,3);
echo "[B5] 'vertraege' toplam gecis: ".substr_count($src,'vertraege')."\n\n";
occ('B6',$src,'Mietvertrag',90,240,6);
occ('B7',$src,'Vertrag Studio',90,240,4);

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
