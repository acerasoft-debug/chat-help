<?php
/** ChatHelp — dump-fix4 (SADECE OKUR) — mevcut tum acik sorunlar icin kod.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
function ALLW($src,$needle,$before,$len,$label,$max=6){
    echo "──────── $label (ilk $max) ────────\n"; $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n"; $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n"; echo "\n";
}
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');

echo "###### 1) SOHBET KATALOG (docs in chat) + 'undefined' tier ######\n";
if($idx!==false){
    W($idx,'Wählen Sie das Dokument',-400,500,"'Wählen Sie das Dokument' render civari");
    W($idx,'function showDocs',0,900,"showDocs (varsa)");
    W($idx,'function pickCat',0,900,"pickCat (varsa)");
    W($idx,'function catPick',0,900,"catPick (varsa)");
    W($idx,'function openCat',0,900,"openCat (varsa)");
    ALLW($idx,'.docs.map',-160,300,"'.docs.map' (chat doc list render)",4);
    ALLW($idx,'CATS.vertraege',-40,160,"CATS.vertraege kullanimi",4);
    W($idx,"cat==='vertraege'",-80,200,"vertraege ozel yonlendirme (varsa)");
    W($idx,'d.tier',-160,220,"d.tier render (undefined kaynagi)");
}

echo "\n###### 2) PLACEHOLDER RESTORE ([VORNAME] neden kaliyor) ######\n";
if($idx!==false){
    W($idx,'"[TELEFON]":',-360,700,"placeholder map + uygulama (split.join)");
    ALLW($idx,'split(k).join',-260,360,"placeholder degistirme dongusu",4);
    W($idx,'lokal eingesetzt',-500,300,"'lokal eingesetzt' not (hangi fonksiyon)");
}

echo "\n###### 3) SOHBET PDF ([[DOC]] karti 'Als PDF speichern') ######\n";
if($idx!==false){
    ALLW($idx,'Als PDF speichern',-220,260,"'Als PDF speichern' buton",4);
    W($idx,'IHR DOKUMENT',-200,500,"[[DOC]] kart render");
    W($idx,'[[DOC',-200,600,"[[DOC]] parse/isle");
    ALLW($idx,'makePDF(',-140,120,"makePDF cagrilari",8);
}

echo "\n###### 4) ODEME ROUND-TRIP (metin kaybolmasin) ######\n";
if($idx!==false){
    W($idx,'function showPaywall',0,900,"showPaywall (varsa)");
    ALLW($idx,'paywall',-80,140,"paywall gecisleri",6);
    ALLW($idx,'checkout',-100,160,"checkout/stripe",5);
    W($idx,'ch_pending',-120,300,"ch_pending (bekleyen belge, varsa)");
    W($idx,'return_url',-120,260,"return_url / geri donus");
}

echo "\n###### 5) MENÜ SIRALAYICI + ANTRÄGE KALICILIK ######\n";
if($idx!==false){
    W($idx,'getElementById("nav-verlauf"),',-700,1300,"MEVCUT nav siralayici");
    W($idx,'function loadHistory',0,1300,"loadHistory()");
}
if($api!==false){
    W($api,'function doKeep',0,600,"doKeep()");
    W($api,"=> doGenerate",-300,700,"dispatch (generate/history)");
    W($api,'function doGenerate',0,1600,"doGenerate basi (kayit/docId)");
    foreach(['INSERT','REPLACE','file_put_contents','->prepare','$sid'] as $k)
        echo "  api '$k' -> ".substr_count($api,$k)." kez\n";
}
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fix4.php ===\n";
