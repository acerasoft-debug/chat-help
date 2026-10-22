<?php
/** ChatHelp — dump-hero (SADECE OKUR) — ana sayfa hero: buyuk foto bolumu (foto CTA),
 *  "Ihr Recht. Ihr Schutz." basligi, soldan-saga lazer animasyonu (@keyframes).
 *  TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] 'Ihr Recht' / 'Schutz' basligi ════\n";
raw($idx,'Ihr Recht',-80,220,'Ihr Recht',6);
raw($idx,'Schutz',-80,120,'Schutz',8);

echo "\n════ [B] buyuk foto bolumu (foto CTA / hero) ════\n";
raw($idx,'Foto',-80,160,'Foto',14);
raw($idx,'foto-cta',-60,180,'foto-cta',5);
raw($idx,'ch-foto',-60,180,'ch-foto*',6);
raw($idx,'Dokument analy',-80,180,'Dokument analysieren',5);
raw($idx,'analysieren',-100,140,'analysieren',6);
raw($idx,'hochladen',-100,160,'hochladen (upload CTA)',8);
raw($idx,'wlc-',-40,140,'wlc-* (hero)',10);

echo "\n════ [C] soldan-saga lazer animasyonu ════\n";
raw($idx,'@keyframes',-10,90,'@keyframes (tum)',30);
raw($idx,'laser',-40,120,'laser',6);
raw($idx,'lazer',-40,120,'lazer',4);
raw($idx,'scan',-40,120,'scan',6);
raw($idx,'sweep',-40,120,'sweep',6);
raw($idx,'translateX(-100%)',-80,120,'translateX(-100%) (soldan-saga)',8);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
