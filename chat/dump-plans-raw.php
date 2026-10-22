<?php
/** ChatHelp — dump-plans-raw (SADECE OKUR) — plan bolgelerinin HAM baytlari
 *  str_replace capalari icin: bosluk sikistirmadan, ilgili kod pencerelerini
 *  aynen dokum eder. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
if($src==='') exit("index.php okunamadi\n");

function raw($src,$needle,$pre,$len,$label){
    echo "\n──────── $label ('$needle') ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "(YOK)\n"; return; }
    $s=max(0,$p-$pre);
    $chunk=substr($src,$s,$len);
    /* gorunur \n (satir sonu KORUNUR, sadece isaretlenir); bosluk AYNEN */
    echo "[@$p]\n".str_replace(["\r"],[""],$chunk)."\n[/end]\n";
}

/* 1) fonksiyonel PLANS haritasi (priceId + docs kotasi) — basic..elite tam */
raw($src,'const PLANS={',0,900,'1) PLANS haritasi (docs kotalari burada)');

/* 2) docs kota kontrolu — 'docs' limitinin karsilastirildigi yer(ler) */
raw($src,'.docs',-120,240,'2a) .docs ilk kullanim');
$off=0;$i=0;
while(($p=strpos($src,'.docs',$off))!==false && $i<8){
    echo "\n  .docs[@$p]: ...".substr($src,max(0,$p-60),140)."...\n";
    $off=$p+5;$i++;
}

/* 3) nList (kart ozellik metinleri: '20 Dok.' vs) */
raw($src,'feats:\'20 Dok',-40,120,'3a) nList basic feats');
raw($src,'feats:\'50 Dok',-40,140,'3b) nList pro feats');
raw($src,'feats:\'300 Dok',-40,160,'3c) nList elite feats');

/* 4) plans[] dizisi (pricing kart UI: feats listeleri) */
raw($src,"const plans=[",0,1400,'4) plans[] pricing kartlari');

/* 5) Vize modulu plan-gate baglantisi: nav-trvisa / CH_TR_VISA / addHomeCard */
raw($src,'CH_TR_VISA_MODULE',0,200,'5a) TR visa modul basi');
raw($src,'nav-trvisa',-80,160,'5b) nav-trvisa gecisi');
raw($src,'trvisa',-60,120,'5c) trvisa ilk gecis');

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
