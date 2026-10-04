<?php
/** ChatHelp — dump-result (SADECE OKUR) — ACIL: normal sablon sonucunda "PDF
 *  cikar" butonu belirgin degil/calismiyor + bos alanlar dolmuyor.
 *  [1] showResult TAM govde (sonuc karti + PDF butonu)
 *  [2] normal sablon uretimi: generate cagrisi + sonucu gosterme
 *  [3] PDF butonu onclick'leri (dlDoc/makePDF/chDlGate bu akista)
 *  [4] doFollowup / eksik-alan sorma tetigi (bos yerleri AI sorsun)
 *  [5] '[' placeholder kalintilari (bos alan isareti) uretimde
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function fnBody($s,$decl,$cap=2200){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kesildi)":"");
}
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] showResult TAM ════\n";
echo fnBody($idx,'function showResult(',2600)."\n";
echo "\n-- window.showResult (varsa ayri) --\n";
echo fnBody($idx,'window.showResult=function(',1800)."\n";

echo "\n════ [2] normal sablon uretim + sonuc ════\n";
raw($idx,"action=generate",-60,140,'generate cagrisi',5);
raw($idx,"'generate'",-40,120,'generate (tirnak)',5);
raw($idx,'showResult(',-30,90,'showResult cagrilari',8);

echo "\n════ [3] PDF butonu onclick ════\n";
raw($idx,'Als PDF',-120,160,'Als PDF buton',6);
raw($idx,'PDF erstellen',-120,160,'PDF erstellen buton',5);
raw($idx,'dlDoc(',-40,90,'dlDoc cagrisi',6);
raw($idx,'chDlGate',-30,90,'chDlGate (bu akista)',8);

echo "\n════ [4] eksik-alan sorma (followup) ════\n";
raw($idx,'followup',-40,140,'followup cagrisi',5);
raw($idx,'needs_more',-30,120,'needs_more',5);
raw($idx,'fehlt',-30,110,'fehlt/eksik',4);

echo "\n════ [5] placeholder kalintilari ════\n";
raw($idx,'BETRAG',-20,80,'[BETRAG] vb placeholder',5);
raw($idx,'______',-20,70,'alt cizgi bosluk',5);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
