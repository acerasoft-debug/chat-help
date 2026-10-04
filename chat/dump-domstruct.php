<?php
/** ChatHelp — dump-domstruct (SADECE OKUR) — onceki 2 dump dar pencere yuzunden
 *  asil html yapisini gostermedi. Bu sefer GENIS pencere (kucuk pre, buyuk post)
 *  ile: #wlc'nin acilis etiketi + ilk 1200 bayti (dogrudan cocuklari), #mi ve
 *  #ia'nin #wlc icinde mi disinda mi oldugu, #tb-flag'in TAM ic yapisi, "Gezielte
 *  Fragen" kartini SARAN div'in class'i, foto-CTA kartini saran div'in class'i.
 *  Ayrica letter-analiz: analyze_photo istemci-tarafi cagrisi + doPhoto() govdesi
 *  (sonucun history'ye eklenip eklenmedigini gormek icin). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$api=(string)@file_get_contents(__DIR__.'/api.php');
if($idx==='') exit("index.php okunamadi\n");

function wide($src,$needle,$pre,$post,$label,$max=2){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p]\n".substr($src,max(0,$p-$pre),$pre+$post)."\n[/end]\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [A] #wlc yapisi ════\n";
wide($idx,'id="wlc"',20,1300,'wlc acilis + ilk cocuklar',1);

echo "\n════ [B] #mi ve #ia konumu ════\n";
wide($idx,'id="mi"',300,60,'mi cevresi (onceki 300 bayt onemli)',1);
wide($idx,'id="ia"',300,60,'ia cevresi (onceki 300 bayt onemli)',1);

echo "\n════ [C] #tb-flag tam ic yapisi ════\n";
wide($idx,'id="tb-flag"',20,700,'tb-flag tam govde',1);

echo "\n════ [D] Gezielte Fragen kartini SARAN div ════\n";
wide($idx,'de:"Gezielte Fragen',400,60,'CLAIM tanimindan ONCEKI 400 bayt (host card burada olabilir)',1);
wide($idx,'⚡</div>',-1,0,'', 0); // no-op guard
wide($idx,'wlc-usp',20,400,'wlc-usp class (tahmin)',2);

echo "\n════ [E] foto-CTA kartini SARAN div ════\n";
wide($idx,'wlc-scan',20,500,'wlc-scan class (tahmin — CSS\'te wlc-scan-t gorduk)',2);

echo "\n════ [F] hub-fab tam CSS+HTML ════\n";
wide($idx,'ch-hub-fab',20,500,'hub-fab tanim + olusturma',2);

echo "\n════ [G] analyze_photo istemci cagrisi ════\n";
wide($idx,'action=analyze_photo',300,700,'foto analiz cagrisi + yanit isleme',2);
wide($idx,"'analyze_photo'",300,500,'alternatif yazim',2);

echo "\n════ [H] doPhoto() govdesi (api.php) ════\n";
$p=strpos($api,'function doPhoto');
echo $p!==false ? substr($api,$p,1800)."\n" : "(YOK)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
