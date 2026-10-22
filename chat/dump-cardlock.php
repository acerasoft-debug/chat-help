<?php
/** ChatHelp — dump-cardlock (SADECE OKUR) — kart savasinin kaniti:
 *  [1] Kart-polisi katmanlarinin TAM govdesi (kim KART SILIYOR?):
 *      ch-cardlock-js, ch-cardowner-js, ch-ui-stability-js, ch-catlock-js
 *  [2] Ana renderer'daki ulke filtresi (countryOf + grid temizleme)
 *  [3] CC'ye YAZAN herkes (ulke kodu titriyorsa kartlar da titrer)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
if($src==='') exit("index.php okunamadi\n");

function scriptBody($src,$id,$cap=7000){
    echo "\n════════ <script id=\"$id\"> ════════\n";
    $tag='<script id="'.$id.'">';
    $p=strpos($src,$tag);
    if($p===false){ echo "(YOK)\n"; return; }
    $e=strpos($src,'</script>',$p);
    $body=substr($src,$p+strlen($tag),$e-$p-strlen($tag));
    $L=strlen($body);
    if($L<=$cap){ echo $body."\n"; }
    else { echo substr($body,0,$cap-1800)."\n\n… [ORTA ATLANDI — toplam $L bayt] …\n\n".substr($body,-1600)."\n"; }
}

/* [1] kart polisleri */
scriptBody($src,'ch-cardlock-js');
scriptBody($src,'ch-cardowner-js');
scriptBody($src,'ch-ui-stability-js',5000);
scriptBody($src,'ch-catlock-js',4000);

/* [2] ana renderer ulke filtresi + grid temizligi */
echo "\n════════ ana renderer: countryOf / grid temizleme ════════\n";
$p=strpos($src,'function countryOf');
if($p!==false) echo "[countryOf @$p]\n".substr($src,$p,900)."\n";
else echo "(function countryOf YOK)\n";
foreach(['grid.innerHTML','removeChild','.wcat").forEach','querySelectorAll(".wcat")'] as $n){
    $off=0;$i=0;$tot=substr_count($src,$n);
    echo "\n-- '$n' (toplam $tot) --\n";
    while(($q=strpos($src,$n,$off))!==false && $i<6){
        echo "[@$q] ".str_replace("\n"," ⏎ ",substr($src,max(0,$q-120),300))."\n";
        $off=$q+strlen($n);$i++;
    }
}

/* [3] CC'ye yazanlar */
echo "\n════════ CC'ye yazan herkes ════════\n";
if(preg_match_all('/[^A-Za-z0-9_$.]CC\s*=(?![=])/',$src,$m,PREG_OFFSET_CAPTURE)){
    $n=0;
    foreach($m[0] as $hit){
        if($n++>=24){ echo "… (fazlasi kesildi, toplam ".count($m[0]).")\n"; break; }
        $q=$hit[1];
        echo "[@$q] ".str_replace("\n"," ⏎ ",substr($src,max(0,$q-90),240))."\n\n";
    }
    echo "(toplam ".count($m[0])." yazim)\n";
} else echo "(YOK)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
