<?php
/** ChatHelp — dump-stab (SADECE OKUR) — CH_STAB_FINAL reveal/settle gecikmesi (~5sn).
 *  reveal/resettle/ch-settling mekanizmasinin TAM kodu — gecikmeyi 1-2sn'ye
 *  indirmek + max-cap eklemek icin.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ CH_STAB_FINAL bloklari ════\n";
raw($idx,'CH_STAB_FINAL',-20,80,'CH_STAB_FINAL isaretleri',12);

echo "\n════ reveal / settle / ch-settling govde ════\n";
/* ch-settling script blogunu bul */
$p=strpos($idx,'ch-settling');
if($p!==false){
  $st=strrpos(substr($idx,0,$p),'<script');
  $en=strpos($idx,'</scr'.'ipt>',$p);
  if($st!==false && $en!==false){ echo "── ch-settling script (@$st..$en) ──\n".substr($idx,$st,min($en-$st+9,3200))."\n[/end]\n"; }
}
raw($idx,'setTimeout(reveal',-80,140,'setTimeout(reveal ...)',5);
raw($idx,'function reveal',0,300,'reveal fn',3);
raw($idx,'function resettle',0,300,'resettle fn',3);
raw($idx,'ch-settling',-40,120,'ch-settling kullanimi',8);
raw($idx,'.ch-settling',-30,140,'.ch-settling CSS',5);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
