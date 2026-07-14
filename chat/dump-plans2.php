<?php
/** ChatHelp — dump-plans2 (SADECE OKUR) — calisan checkout fn (fetch+yanit redirect)
 *  + stripe-checkout.php cikti formati + plan fiyatlari + Tarife tetikleme.
 *  Hassas maskeli. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk_live_|sk_test_|whsec_|price_)[A-Za-z0-9_]+/','$1***',$s); }
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$sc=(string)@file_get_contents(__DIR__.'/stripe-checkout.php');
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",mask(substr($s,max(0,$from),$len)))."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",mask(substr($src,max(0,$p-$pre),$len)))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════ [1] calisan checkout fonksiyonu (@744300,+1300) ════";
sl($idx,744300,1300,'checkout fn + yanit');

echo "\n════ [2] stripe-checkout.php cikti (@2700-3302) ════";
sl($sc,2650,660,'stripe-checkout echo/cikti');

echo "\n════ [3] plan fiyatlari + Tarife/Preise UI ════\n";
raw($idx,'13,99',-60,80,'Basic 13,99',4);
raw($idx,'Tarife',-60,120,'Tarife',6);
raw($idx,'Preise',-60,100,'Preise',6);
raw($idx,'sPlan',-40,160,'sPlan',6);
raw($idx,"id=\"pm\"",-40,200,'#pm modal',3);
raw($idx,'planKey',-60,140,'planKey',8);
raw($idx,'function pmGo',-20,160,'pmGo/buy fn',4);
raw($idx,'goCheckout',-40,160,'goCheckout',6);
raw($idx,'startCheckout',-40,160,'startCheckout',6);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
