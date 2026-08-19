<?php
/** ChatHelp — dump-geostate (SADECE OKUR) — F2-1 icin: IP-bazli hukuk+dil
 *  varsayilaninin NE KADARI zaten canli? Kanit toplar, tekrar is yapmayi
 *  onler:
 *  [1] detectLoc + CH_CC_LOCK zinciri index.php'de gercekten var mi
 *  [2] geo.php'nin ulke/dil destek listesi (CH_GEO_SUP) ve son hali
 *  [3] api.php'de action=detect_location handler + IP->ulke cozumleme
 *  [4] Vertrag Studio'ya yeni eklenen ES/FR/IT/GB/TR icin geo.php destegi var mi
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");
$geo=(string)@file_get_contents("$D/geo.php");

function raw($src,$needle,$pre,$len,$label,$max=2){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "index.php: ".strlen($idx)." bayt | api.php: ".strlen($api)." bayt | geo.php: ".(strlen($geo)?strlen($geo)." bayt":"DOSYA YOK")."\n";

echo "\n════ [1] detectLoc + CH_CC_LOCK zinciri ════\n";
raw($idx,'CH_CC_LOCK',-40,160,'CH_CC_LOCK izleri',5);
raw($idx,'async function detectLoc',0,500,'detectLoc govdesi',1);
raw($idx,"localStorage.getItem('ch_ldi')",-60,160,'ch_ldi tek-seferlik kapisi',2);
raw($idx,"localStorage.getItem('ch_cc_manual')",-60,200,'manuel kilit kontrolu',3);

echo "\n════ [2] geo.php destek listesi ════\n";
raw($geo,'CH_GEO_SUP',-40,120,'CH_GEO_SUP izi',2);
raw($geo,'$SUP',-20,500,'$SUP tam liste',2);
raw($geo,'CH_GEO_MULTI',-40,160,'CH_GEO_MULTI izi (varsa)',2);

echo "\n════ [3] api.php detect_location ════\n";
raw($api,'detect_location',-60,200,'action router',2);
raw($api,'function doDetectLocation',0,600,'handler govdesi (varsa)',1);
raw($api,'function doGeo',0,400,'doGeo (varsa)',1);

echo "\n════ [4] yeni ulkeler icin geo destegi ════\n";
foreach(['ES','FR','IT','GB','TR','SE','CH','BE'] as $cc){
    $inSup = (strpos($geo,"'$cc'=>")!==false || strpos($geo,"\"$cc\"=>")!==false) ? "VAR" : "YOK";
    echo "  $cc geo.php \$SUP icinde: $inSup\n";
}

echo "\n════ [5] CC_DATA (client) ile geo.php kapsam farki ════\n";
raw($idx,'CC_DATA={',-10,900,'CC_DATA tanimi (ilk 900 bayt)',1);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
