<?php
/** ChatHelp — dump-fix2 (SADECE OKUR)
 *  A) behalten -> sunucuya kayit + Meine Anträge (pnl-ant) listesi + action=history/save.
 *  B) sol menü: TUM nav-* kimlikleri, sidebar (#sb) yapisi, insertBefore anchor'lari,
 *     varsa mevcut sira-sabitleyici.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
function ALLW($src,$needle,$before,$len,$label,$max=12){
    echo "──────── $label (ilk $max) ────────\n";
    $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n";
        $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n"; echo "\n";
}
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
$auth=@file_get_contents(__DIR__.'/auth.php');

echo "###### A) BEHALTEN / ANTRÄGE ######\n";
if($idx!==false){
    W($idx,'function showResult',780,2600,"showResult TAM (behalten butonu dahil)");
    ALLW($idx,'Behalten',-160,260,"'Behalten' buton/etiket",6);
    ALLW($idx,'action=history',-120,200,"action=history cagrilari",4);
    ALLW($idx,"action=save",-120,220,"action=save (kaydetme)",4);
    W($idx,'pnl-ant',-200,700,"pnl-ant (Meine Anträge paneli)");
    W($idx,"gP('ant'",-40,120,"gP('ant') tetikleyici");
    W($idx,'function gP',0,600,"gP() panel gecis fonksiyonu");
}
echo "\n###### api.php: history + save ######\n";
if($api!==false){
    W($api,"'history'",-40,500,"history action (api)");
    W($api,'history',-60,400,"history (ilk gecis, api)");
    ALLW($api,'INSERT INTO',-40,180,"INSERT INTO (kayit, api)",6);
    W($api,'documents',-60,300,"documents tablosu (api)");
}
echo "\n###### auth.php: history + save ######\n";
if($auth!==false){
    W($auth,'history',-60,400,"history (auth)");
    ALLW($auth,'INSERT INTO',-40,180,"INSERT INTO (auth)",6);
}

echo "\n\n###### B) SOL MENÜ SIRASI ######\n";
if($idx!==false){
    ALLW($idx,'id="nav-',-20,90,"nav-* statik id'ler",20);
    ALLW($idx,'.id="nav-',-40,90,"nav-* JS ile atanan id'ler",20);
    ALLW($idx,'getElementById("nav-',-10,60,"getElementById nav-* (guard'lar)",20);
    ALLW($idx,'insertBefore',-140,120,"insertBefore (modul ekleme)",16);
    ALLW($idx,'.sb-photo-btn',-60,120,"'.sb-photo-btn' anchor kullanimi",12);
    W($idx,'id="sb"',-40,900,"#sb sidebar container + statik ic sira");
    /* mevcut sira sabitleyici izi */
    foreach(['CH_MENU','CH_NAV','CH_SIDEBAR','reorder','stabil','MENU_ORDER','NAV_ORDER'] as $k)
        echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
}
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fix2.php ===\n";
