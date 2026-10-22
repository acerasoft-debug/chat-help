<?php
/** ChatHelp — dump-pdfbtn (SADECE OKUR) — "Als PDF speichern" butonu tiklaninca
 *  HICBIR SEY olmuyor (foto okunuyor, belge cikiyor, PDF cikmiyor). Handler'i +
 *  makePDF/chPrintDoc govdesini tam gormeliyim ki nerede sessizce oldugunu bulayim.
 *  [1] "PDF speichern" / "Als PDF" buton HTML'i + onclick handler'i
 *  [2] bu DOC kartini kuran fonksiyon (buton + doc/name degiskenleri)
 *  [3] makePDF TAM govde (chPrintDoc / window.open / print dallari)
 *  [4] chPrintDoc TAM govde (mobilde calisiyor mu)
 *  [5] showPaywall (free ise buraya mi dusuyor)
 *  [6] _tk3/_up3 plan-gate cevresi (@987000-988000)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function fnBody($s,$decl,$cap=1600){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kesildi)":"");
}
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] 'PDF speichern' buton + onclick ════\n";
raw($idx,'PDF speichern',120,220,'PDF speichern',6);
raw($idx,'Als PDF',100,180,'Als PDF',5);

echo "\n════ [2] DOC kart kurulumu (savedoc/keepdoc card / dlDoc) ════\n";
raw($idx,'function dlDoc',0,200,'dlDoc',2);
raw($idx,'IHR DOKUMENT',80,140,'IHR DOKUMENT etiketi',3);
raw($idx,'__chDocPdf',30,120,'__chDocPdf',6);

echo "\n════ [3] makePDF TAM govde ════\n";
echo fnBody($idx,'function makePDF(',2600)."\n";

echo "\n════ [4] chPrintDoc TAM govde ════\n";
echo fnBody($idx,'window.chPrintDoc=function(',1800)."\n";

echo "\n════ [5] showPaywall ════\n";
echo fnBody($idx,'function showPaywall(',1200)."\n";

echo "\n════ [6] plan-gate cevresi (@986900-988200) ════\n";
echo substr($idx,986900,1300)."\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
