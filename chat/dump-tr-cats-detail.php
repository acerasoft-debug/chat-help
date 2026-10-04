<?php
/**
 * ChatHelp — dump-tr-cats-detail (SADECE OKUR) — 2 onarim icin KESIN anchor:
 *  [A] TR dilekce katalogu: TR NEWCATS blogunun TAM metni + ilk tam ornek
 *      girdi + blogun SONU (merge kodu) -> eksik kategorileri (is/tuketici/
 *      icra/aile) ayni kaliba eklemek icin.
 *  [B] "Anzeigen/Ausblenden" dugmelerinin GERCEK kaynagi: kucuk harf
 *      'anzeigen'/'ausblenden', 'blenden', 'Kategorien' baglamlari, ana sayfa
 *      kategori bolumu toggle mekanizmasi.
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-tr-cats-detail.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-tr-cats-detail — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

echo "───── [A] TR katalog yapisi ─────\n\n";

echo "[A1] Ilk 'trx_miras' gecisinden itibaren 2600 karakter (NEWCATS TR + ilk girdiler):\n";
$p=strpos($src,'trx_miras');
if($p!==false) echo substr($src,max(0,$p-300),2600)."\n";
else echo "  (yok)\n";

echo "\n[A2] SON 'trx_' gecisinden itibaren 1600 karakter (blok sonu + merge kodu):\n";
$p=strrpos($src,'trx_');
if($p!==false) echo substr($src,max(0,$p-200),1600)."\n";
else echo "  (yok)\n";

echo "\n───── [B] Anzeigen/Ausblenden kaynagi ─────\n\n";

function occ($label,$src,$needle,$pre=110,$len=340,$max=8){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

occ('B1',$src,'anzeigen',110,340,8);
occ('B2',$src,'usblenden',110,340,6);
occ('B3',$src,'inblenden',110,340,6);
occ('B4',$src,'Kategorien',110,380,8);
occ('B5',$src,'Weniger',90,280,4);
occ('B6',$src,'Alle Themen',90,300,4);

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
