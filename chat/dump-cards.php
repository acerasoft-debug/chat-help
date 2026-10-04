<?php
/** ChatHelp — dump-cards (SADECE OKUR) — ana sayfa kart/kategorilerinden Verträge +
 *  Bewerbung kaldirmak icin. Kategori kartlarinin (wcat/cat/cgrid) anahtarlari,
 *  data-cat, onclick, metinleri. (Vertrag Studio MODULU nav-vst KALIR.)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function raw($src,$needle,$pre,$len,$label,$max=10){
    echo "\n---- $label ('$needle') ----\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","\\n",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "==== [1] kategori kartlari: Vertrag/Verträge ====\n";
raw($idx,'Verträge',-90,120,'Verträge metin',10);
raw($idx,'Vertrag',-40,70,'Vertrag',8);
raw($idx,'data-cat="vert',-30,120,'data-cat vert',6);
raw($idx,'vertraege',-40,90,'vertraege anahtar',8);

echo "\n==== [2] kategori kartlari: Bewerbung ====\n";
raw($idx,'Bewerbung',-90,120,'Bewerbung metin',10);
raw($idx,'bewerbung',-40,90,'bewerbung anahtar',8);
raw($idx,'data-cat="bew',-30,120,'data-cat bew',5);

echo "\n==== [3] kategori kart yapisi (wcat/cat/cgrid) ====\n";
raw($idx,'wcat" data-univcat',-20,150,'wcat data-univcat',5);
raw($idx,'class="cat"',-20,140,'class=cat',5);
raw($idx,'showCat(',-40,80,'showCat cagrisi',6);
raw($idx,'data-cccountry',-30,120,'data-cccountry',4);

echo "\n==== [4] mevcut gizleme kurallari (referans) ====\n";
raw($idx,'ch-vtr-sec',-30,120,'ch-vtr-sec (CH_VERTRAG_BLUR)',4);
raw($idx,'ch-jobs-card',-20,90,'ch-jobs-card (clean1 gizledi)',3);
raw($idx,'ch-vertrag-home',-30,120,'ch-vertrag-home',4);

echo "\n==== BITTI. TAMAMINI gonder. ====\n";
