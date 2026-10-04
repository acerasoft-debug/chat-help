<?php
/** ChatHelp — dump-cat-cc (SADECE OKUR)
 *  A) ENVANTER: ulke basina belge sayisi (genis katalog plani icin).
 *  B) Sidebar KATEGORI bloklarinin kurulumu/sirasi (oynama koku).
 *  C) Hukuk ulkesi (CC) kalicilik: ch_cc_manual set noktalari + tum CC atamalari.
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
function ALLW($src,$needle,$before,$len,$label,$max=8){
    echo "──────── $label (ilk $max) ────────\n"; $off=0;$i=0;
    while(($p=strpos($src,$needle,$off))!==false && $i<$max){
        echo "[$i @$p] ".substr($src,max(0,$p-$before),$len)."\n....\n"; $off=$p+strlen($needle);$i++;
    }
    if($i===0) echo "  (yok)\n"; echo "\n";
}

echo "###### A) ENVANTER — ulke onekiyle belge sayilari ######\n";
foreach(['tr_','fr_','es_','it_','nl_','be_','at_','ch_','pl_','se_','pt_','gb_','us_'] as $pfx){
    /* dokuman tanimi kalibi: pfx_xxx:{ic: */
    $n = preg_match_all('/\b'.$pfx.'[a-z0-9_]+:\{ic:/', $idx, $m);
    echo sprintf("  %s -> %d belge tanimi\n", $pfx, $n);
}
$n = preg_match_all('/\b[a-z]{2}_[a-z0-9_]+:\{n:/', $idx, $m);
echo "  (kategori tanimi topl.: $n)\n\n";
/* DE ana katalog buyuklugu (referans) */
foreach(['wid_','ku_','an_','bes_'] as $pfx){
    $n = preg_match_all('/\b'.$pfx.'[a-z0-9_]+:\{ic:/', $idx, $m);
    echo "  DE-ref '$pfx' -> $n\n";
}

echo "\n###### B) SIDEBAR KATEGORILERI ######\n";
foreach(['sb-cat-hdr','CATS=','CATS[','X_ORD','appendCats','renderCats','buildCats','injectCats'] as $k)
    echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
echo "\n";
W($idx,'sb-cat-hdr',-400,800,"ilk sb-cat-hdr olusumu (statik mi dinamik mi)");
ALLW($idx,'X_ORD',-80,300,"X_ORD tanimlari (ulke kategori siralari)",6);
W($idx,'const CATS',-20,900,"CATS tanimi");
W($idx,'var CATS',-20,900,"CATS tanimi (var)");
ALLW($idx,'CATS[cc]',-120,260,"CATS[cc] dinamik ekleme",5);
ALLW($idx,'.docs.push',-160,240,".docs.push (katalog enjeksiyonlari)",8);

echo "\n###### C) HUKUK ULKESI KALICILIGI ######\n";
foreach(['ch_cc_manual','CH_CC_LOCK','savePref','detect_location','ch_ldi'] as $k)
    echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
echo "\n";
ALLW($idx,'ch_cc_manual',-200,350,"ch_cc_manual tum gecisler",8);
ALLW($idx,'CC=',-100,200,"CC= atamalari",12);
W($idx,'function applyCC',0,700,"applyCC");
W($idx,'function setCountry',0,700,"setCountry (varsa)");
W($idx,'sb-country',-150,500,"sb-country tiklama akisi");
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-cat-cc.php ===\n";
