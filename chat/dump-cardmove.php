<?php
/** ChatHelp — dump-cardmove (SADECE OKUR) — kart oynamasi teshisi:
 *  .wcat markup + data-attribute'lari, stabilizer (.ch-settling / CH_STAB),
 *  CH_CARDS_CLEAN blogu, reveal zamanlamasi. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [A] .wcat kart markup + attribute'lar ════\n";
raw($idx,'wcat',-40,240,'wcat gecisler',12);
raw($idx,'data-univcat',-30,90,'data-univcat',10);
raw($idx,'data-ccat',-30,90,'data-ccat',8);
raw($idx,'data-cat=',-30,90,'data-cat',8);

echo "\n════ [B] stabilizer / oynama onleyici ════\n";
raw($idx,'ch-settling',-60,160,'ch-settling',10);
raw($idx,'CH_STAB',-40,120,'CH_STAB markerlari',12);
raw($idx,'cardorder',-40,120,'cardorder',6);
raw($idx,'freeze',-40,120,'freeze',8);

echo "\n════ [C] CH_CARDS_CLEAN blogu (bu oturumda eklendi) ════\n";
raw($idx,'CH_CARDS_CLEAN',-40,140,'CH_CARDS_CLEAN',6);
raw($idx,'ch-cards-clean',-20,900,'ch-cards-clean tam blok',3);

echo "\n════ [D] reveal / reflow (apply-speed) ════\n";
raw($idx,'CH_SPEED',-40,120,'CH_SPEED',6);
raw($idx,'.wcat',-20,60,'.wcat CSS selector',10);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
