<?php
/** ChatHelp — dump-cardflicker (SADECE OKUR) — kartlar "gidiyor-geliyor-gidiyor"
 *  dongusunun kaynagini bulmak icin:
 *  [1] ch_zen kalintilari (kalicilik devplan-fix ile kalkti mi?)
 *  [2] body class'ina yazan herkes (ch-zen/ch-cc-nonde sinifini dusuren var mi?)
 *  [3] Kart izgarasini (wcat-grid / vitrin / co-grid) yeniden kuran/gizleyen kod
 *  [4] Hangi script id'sinde kac setInterval var (savasan dongulerin haritasi)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
if($src==='') exit("index.php okunamadi\n");

function occ($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "[1] ch_zen kalintilari\n";
occ($src,'ch_zen',-60,200,'ch_zen gecisleri',8);
occ($src,'CH_ZEN_NOPERSIST',-40,120,'devplan-fix zen yamasi izi',2);

echo "\n[2] body class yazanlar\n";
occ($src,'document.body.className',-60,220,'body.className yazimi',6);
occ($src,'body.classList.remove',-60,200,'body.classList.remove',8);
occ($src,'body.classList.toggle',-60,200,'body.classList.toggle',8);
occ($src,'ch-cc-nonde',-80,220,'ch-cc-nonde uygulayan kod',6);

echo "\n[3] kart izgarasi kuran/gizleyen kod\n";
occ($src,'wcat-grid',-100,260,'wcat-grid gecisleri',10);
occ($src,'renderCats',-60,180,'renderCats cagrilari',8);
occ($src,'ch-vtr-sec',-80,220,'sozlesme vitrini',6);
occ($src,'ch-vst-home',-80,220,'vst-home vitrini',6);

echo "\n[4] script basina setInterval sayimi\n";
$parts=preg_split('#(<script[^>]*>)#',$src,-1,PREG_SPLIT_DELIM_CAPTURE);
$cur='(inline/ilk)';
for($i=0;$i<count($parts);$i++){
    if(preg_match('#^<script[^>]*>$#',$parts[$i])){
        $cur=$parts[$i];
        if(preg_match('#id="([^"]+)"#',$cur,$m)) $cur=$m[1];
        continue;
    }
    $c=substr_count($parts[$i],'setInterval(');
    $w=substr_count($parts[$i],'wcat')+substr_count($parts[$i],'wlc')+substr_count($parts[$i],'vtr-sec');
    if($c>0) echo sprintf("  %-32s setInterval:%d  (wlc/kart temasi:%d)\n",$cur,$c,$w);
}
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
