<?php
/** ChatHelp — dump-fotoread (SADECE OKUR) — ana sayfa foto okuyucu:
 *  analyze fonksiyonu + analyze_photo tetikleyici + global foto-oku fn +
 *  mevcut composer foto butonlari (ch-att-btn / chp / cha2). TAMAMINI gonder. */
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
echo "════ [A] analyze / foto-oku fonksiyonu ════\n";
raw($idx,'async function analyze',-20,200,'async analyze',4);
raw($idx,'function analyze',-20,200,'analyze fn',6);
raw($idx,'window.chAnalyze',-20,120,'window.chAnalyze',4);
raw($idx,'window.chReadPhoto',-20,120,'chReadPhoto',4);
raw($idx,'window.chPhotoRead',-20,120,'chPhotoRead',4);
raw($idx,'Dokument wird gelesen',-200,120,'"wird gelesen" toast (analiz basi)',4);

echo "\n════ [B] ana composer foto girisi + analyze tetik ════\n";
raw($idx,"inp.files[0]",-260,120,'inp.files[0] (analyze girisi)',6);
raw($idx,'chPhotoPick',-40,180,'chPhotoPick',6);
raw($idx,'data-ctx="main"',-120,200,'chp main buton',3);

echo "\n════ [C] benim 📎 ataç (composer-premium) + attach-plus ════\n";
raw($idx,'ch-att-btn',-30,160,'ch-att-btn (benim 📎)',4);
raw($idx,'CH_COMPOSER_PREMIUM',-20,120,'composer premium marker',3);
raw($idx,'_chAtt',-20,120,'_chAtt store',6);
raw($idx,'.chp{display:none',-60,80,'chp global gizleme (attach-plus)',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
