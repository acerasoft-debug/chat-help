<?php
/**
 * ChatHelp — dump-mainpage-state (SADECE OKUR) — 3 sikayet icin teshis:
 *  [A] Ana sayfa kategorilerdeki "Anzeigen/Ausblenden" dugmeleri calismiyor
 *      -> dugmelerin TAM HTML'i + onclick handler'lari + ilgili JS fonksiyonu
 *  [B] Vize bolumu eksikleri -> tum CH_VIZE marker'lari + DEST/TYPE/GENMETA
 *      sayilari + chVizeAsistan durumu
 *  [C] Turkiye dilekce katalogu -> trx_ kategorileri + TR belge sayisi
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-mainpage-state.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-mainpage-state — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=120,$len=420,$max=6){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

echo "───── [A] Anzeigen/Ausblenden ─────\n\n";
occ('A1',$src,'Anzeigen',100,340,8);
occ('A2',$src,'Ausblenden',100,340,8);
occ('A3',$src,'anzeigen<',80,280,6);
occ('A4',$src,'ausblenden<',80,280,6);

echo "───── [B] Vize Asistani ─────\n\n";
foreach(['CH_VIZE_ASISTAN','CH_VIZE_UX','CH_VIZE_RDV','CH_VIZE_DATEFIX','CH_VIZE_SELFEMP','chVizeAsistan'] as $m)
  echo "  ".str_pad($m,18).": ".substr_count($src,$m)." kez\n";
echo "\n";
occ('B1',$src,'function chVizeAsistan',10,500,1);
occ('B2',$src,'DEST=[',10,700,1);
occ('B3',$src,'GENMETA',60,260,3);

echo "───── [C] Turkiye dilekce katalogu ─────\n\n";
echo "  'trx_' toplam: ".substr_count($src,'trx_')." kez\n";
echo "  '\"tr\"' katalog anahtari kontrolleri:\n";
foreach(['trx_miras','trx_kira','trx_kat_mulkiyeti','trx_is','trx_tuketici','trx_icra','trx_aile','trx_vize'] as $m)
  echo "  ".str_pad($m,20).": ".substr_count($src,'"'.$m.'"')." kez\n";
echo "\n";
occ('C1',$src,'NEWCATS={',10,700,1);

echo "───── [D] Mikrofon marker durumu ─────\n\n";
foreach(['CH_MIC_APPTO','CH_MIC_WVDIRECT','CH_MICPRO2','CH_MICSMART2','chKbFocus','chOpenMic'] as $m)
  echo "  ".str_pad($m,18).": ".substr_count($src,$m)." kez\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
