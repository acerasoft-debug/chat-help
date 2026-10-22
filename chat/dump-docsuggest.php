<?php
/** ChatHelp — dump-docsuggest (SADECE OKUR) — iki konu icin harita:
 *  [A] apply-vst-tr.php'in "boot anchor 2 kez" hatasi -> her iki gecisin
 *      cevresini goster, hangi modullere ait oldugunu ayirt et.
 *  [B] "📄 Dokument erstellen" cip'i tiklaninca hicbir sey olmuyor bug'i ->
 *      SUGG_MAP/showSuggestChip GUNCEL hali, action=detect endpoint'i,
 *      DOCS/CD/showQuestions varligi, chOpenDev tanimi.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;

function allOcc($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        echo "[@$p]\n".substr($src,$s,$len)."\n[/end]\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n";
    echo "(toplam $tot)\n";
}
function one($src,$needle,$pre,$len,$label){
    echo "\n──────── $label ('$needle') ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "(YOK)\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$pre),$len)."\n[/end]\n";
}

$idx=(string)@file_get_contents("$D/index.php");
echo "════════════════ index.php ════════════════\n";

/* [A] boot anchor - her iki gecis + her birinin en yakinindaki <script id=...> */
$anchor='function boot(){ addCss(); addNav();';
$positions=[]; $off=0;
while(($p=strpos($idx,$anchor,$off))!==false){ $positions[]=$p; $off=$p+strlen($anchor); }
echo "\n──────── [A] boot anchor konumlari (toplam ".count($positions).") ────────\n";
foreach($positions as $i=>$p){
    echo "\n-- gecis #$i @$p --\n";
    echo substr($idx,max(0,$p-200),500)."\n";
    $sidPos = strrpos(substr($idx,0,$p), '<script id="');
    if($sidPos!==false){
        $endQ = strpos($idx,'"',$sidPos+13);
        echo "   en yakin onceki <script id=...>: ".substr($idx,$sidPos,($endQ!==false?$endQ-$sidPos+1:60))." (uzaklik ".($p-$sidPos)." bayt)\n";
    }
}

/* [B] SUGG_MAP / showSuggestChip guncel hali */
one($idx,'var SUGG_MAP=',0,700,'SUGG_MAP tanimi');
one($idx,'function showSuggestChip',0,700,'showSuggestChip guncel govde');
one($idx,'function chAiChatTurn',0,900,'chAiChatTurn guncel govde (userText erisimi icin)');

/* action=detect endpoint + DOCS/CD/showQuestions varligi */
allOcc($idx,'action=detect',-100,400,'action=detect kullanim noktalari',5);
foreach(['function showQuestions','var DOCS=','let DOCS=','const DOCS=','var CD','let CD','function chOpenDev','window.chOpenDev'] as $k)
    echo "\n  '$k' -> ".substr_count($idx,$k)." kez\n";
one($idx,'function chOpenDev',0,300,'chOpenDev guncel govde');

$api=(string)@file_get_contents("$D/api.php");
echo "\n════════════════ api.php ════════════════\n";
allOcc($api,"'detect'",-150,600,"'detect' action tanimi/router",4);
one($api,'function doDetect',0,800,'doDetect govdesi (varsa)');

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
