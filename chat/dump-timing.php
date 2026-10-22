<?php
/** ChatHelp — dump-timing (SADECE OKUR) — kartlar/yazilar ~5sn'de geliyor, 1-2'ye inecek.
 *  Buyuk gecikmeli setTimeout/setInterval'leri ve kart enjektorlerinin ilk-gosterim
 *  bekleme surelerini bulur.
 *  [1] setTimeout(...,>=800ms) baglamlariyla
 *  [2] setInterval gecikmeleri
 *  [3] kart/hub/render enjektor ilk-bekleme (ch-hub-card, ch-jobs, wcat, render)
 *  [4] onboarding/init geciktiren yerler (t===3, DOMContentLoaded sonrasi bekleme)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function occDelay($src,$pat,$label){
    echo "\n──────── $label ────────\n";
    if(preg_match_all($pat,$src,$m,PREG_OFFSET_CAPTURE)){
        $seen=0;
        foreach($m[0] as $i=>$hit){
            $p=$hit[1];
            echo "[@$p] ".str_replace("\n"," ",substr($src,max(0,$p-70),150))."\n";
            if(++$seen>=20) break;
        }
        echo "(toplam ".count($m[0]).")\n";
    } else echo "(YOK)\n";
}
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-60),150))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] setTimeout gecikmeleri (>=800ms) ════\n";
occDelay($idx,'/setTimeout\([^,;]{0,60},\s*(8\d\d|9\d\d|1\d{3,}|2\d{3,}|3\d{3,}|[4-9]\d{3,})\s*\)/','setTimeout buyuk gecikme');

echo "\n════ [2] setInterval gecikmeleri ════\n";
occDelay($idx,'/setInterval\([^,;]{0,40},\s*(\d{3,})\s*\)/','setInterval');

echo "\n════ [3] kart/render enjektor ilk-bekleme ════\n";
raw($idx,'ch-hub-card',-40,120,'hub card',4);
raw($idx,'ch-jobs-card',-40,120,'jobs card',3);
raw($idx,'function render(',0,120,'render fn',6);
raw($idx,'renderCards',-20,110,'renderCards',4);
raw($idx,'showAllCats',-20,110,'showAllCats',5);
raw($idx,'wcat',-20,90,'wcat (module hub kart)',6);

echo "\n════ [4] init/onboarding gecikme ════\n";
raw($idx,'DOMContentLoaded',-20,120,'DOMContentLoaded',8);
raw($idx,'t===3',-40,110,'t===3 onboard',3);
raw($idx,'t++<80',-40,110,'run loop t++<80',3);
raw($idx,'requestAnimationFrame',-20,90,'rAF',4);
raw($idx,'boot()',-30,100,'boot()',8);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
