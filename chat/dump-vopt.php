<?php
/** ChatHelp — dump-vopt (SADECE OKUR) — VOPT opsiyon-kloz render (checkbox adim),
 *  optPages baglama, VOPT tam yapisi + FR/ES/IT/GB sozlesme kategori key'leri.
 *  TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] VOPT checkbox adim render (opsiyon secimi) ════\n";
raw($idx,'vst-cbrow',-200,240,'vst-cbrow (checkbox render)',5);
raw($idx,'VOPT[',-40,160,'VOPT[ erisim',10);
raw($idx,'Zusatzklauseln',-80,180,'Zusatzklauseln adim',6);
raw($idx,'optPages',-40,160,'optPages',6);
raw($idx,"==='JA'",-80,80,"JA secim",8);

echo "\n════ [B] VOPT step nasil CT'ye ekleniyor ════\n";
raw($idx,'__uc',-120,200,'__uc (opsiyon adim ekleme)',4);
raw($idx,'m.c.filter',-80,160,'m.c.filter',4);

echo "\n════ [C] FR/ES/IT/GB sozlesme kategori key'leri ════\n";
raw($idx,'cat:"fr_',-20,60,'fr_ kategoriler',20);
raw($idx,'contrat',-30,50,'fr contrat kategori',8);
raw($idx,'es_contratos:',-20,120,'es_contratos tanim',3);
raw($idx,'it_contratti:',-20,120,'it_contratti tanim',3);
raw($idx,'gb_contracts',-30,90,'gb_contracts',5);
raw($idx,'fr_contrats:',-20,120,'fr_contrats tanim',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
