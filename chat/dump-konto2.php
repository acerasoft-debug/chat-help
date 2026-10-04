<?php
/** ChatHelp — dump-konto2 (SADECE OKUR) — ust barda hem ad(profil) hem "Konto"
 *  yaziyor; hangi eleman "Konto" diyor bulunacak (biri kaldirilacak).
 *  [1] ust bar bolgesi (@847000-853000) — tum butonlar/etiketler
 *  [2] gorunur "Konto" metni gecen yerler (tb-*, buton, span)
 *  [3] "Kullanıcı profili"/"Profil"/"Mein Konto" ust bar etiketleri
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,max(0,$from),$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=10){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] ust bar bolgesi (tb-r) ════\n";
$p=strpos($idx,'class="tb-r"');
if($p!==false) slice($idx,max(0,$p-40),1600,'tb-r @'.($p-40));
else slice($idx,850900,1500,'topbar civari @850900');

echo "\n════ [2] gorunur 'Konto' metinleri ════\n";
raw($idx,'>Konto<',-90,120,'>Konto< etiket',8);
raw($idx,'Konto</span>',-100,130,'Konto</span>',6);
raw($idx,'Mein Konto',-60,120,'Mein Konto',5);
raw($idx,'>Mein Konto',-60,120,'>Mein Konto',3);
raw($idx,'tb-konto',-60,140,'tb-konto',4);

echo "\n════ [3] profil / kullanici etiketleri (ust bar) ════\n";
raw($idx,'Kullanıcı',-40,120,'Kullanici',4);
raw($idx,'tb-user',-60,160,'tb-user',5);
raw($idx,'tb-auth-label',-120,160,'tb-auth-label cevresi',3);
raw($idx,'tb-country-name',-100,140,'tb-country-name',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
