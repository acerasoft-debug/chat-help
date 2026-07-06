<?php
/** ChatHelp — dump-antraege-menu (SADECE OKUR)
 *  (1) "Meine Anträge" + "behalten": belge kaydi/listesi neden kalici degil.
 *  (2) Sol menü: modul sirasinin nasil kuruldugu (neden oynuyor).
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php okunamadi\n");
echo "index.php: ".strlen($idx)." bayt\n\n";

function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
function ALLW($src,$needle,$before,$len,$label,$max=5){
    echo "──────── $label (ilk $max) ────────\n";
    $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n";
        $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n"; echo "\n";
}

echo "###### (1) MEINE ANTRÄGE / BEHALTEN ######\n";
foreach(['behalten','Behalten','ch_docs','ch_saved','savedDocs','recentDocs','loadRecentDocs','meineAntraege','antraege','Anträge','ch_antraege','saveDoc','ch_dv'] as $k)
    echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
echo "\n";
ALLW($idx,'behalten',-120,320,"behalten buton+handler",4);
W($idx,'function showResult',0,1100,"showResult (behalten butonu buradan)");
W($idx,'function loadRecentDocs',0,900,"loadRecentDocs (liste yukleme)");
W($idx,'function saveDoc',0,700,"saveDoc (varsa)");
ALLW($idx,'ch_docs',-80,200,"ch_docs storage anahtari",6);
W($idx,'Meine Anträge',-200,400,"'Meine Anträge' panel/etiket");
W($idx,'renderDocs',0,700,"renderDocs (liste render, varsa)");

echo "\n###### (2) SOL MENÜ SIRASI ######\n";
foreach(['addNav','renderNav','navOrder','X_ORD','MOD_ORDER','sidebar','sb-cat','sortNav','buildNav','.sort(','__vstList','order'] as $k)
    echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
echo "\n";
W($idx,'function addNav',0,1200,"addNav (menü kurulumu)");
W($idx,'function renderNav',0,1000,"renderNav (varsa)");
W($idx,'X_ORD',-40,500,"X_ORD (kategori sirasi, varsa)");
ALLW($idx,'.sort(',-120,180,"'.sort(' cagrilari (siralama)",8);
ALLW($idx,'appendChild',-60,140,"menü appendChild (dinamik ekleme)",8);
W($idx,'sb-cat',-120,400,"sb-cat (sidebar kategori) civari");

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-antraege-menu.php ===\n";
