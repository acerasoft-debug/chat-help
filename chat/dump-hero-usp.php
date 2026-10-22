<?php
/** ChatHelp — dump-hero-usp (SADECE OKUR) — "Ihr Recht. Ihr Schutz." basligi
 *  ile "Gezielte Fragen · klares Ziel" USP kartinin TAM html uretim kodunu
 *  bulur (birlestirme yamasi icin gerekli). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function occ($src,$needle,$pre,$len,$label,$max=3){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
occ($idx,'Ihr Recht',-200,700,'Ihr Recht. Ihr Schutz. baslik uretimi',3);
occ($idx,'wlc-h1',-150,400,'wlc-h1 class kullanimlari',3);
occ($idx,'Gezielte Fragen',-300,750,'USP karti tam baglam',3);
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
