<?php
/** ChatHelp — dump-welcome-struct (SADECE OKUR — ekrana hicbir sey eklemez)
 *  AMAC: "ES'te menuler oynuyor" icin kesin kimlik. Canli index.php kaynagindan:
 *   [1] .wcat-grid'in olusturuldugu/yerlestirildigi yerler (sarmalayici eleman kim?)
 *   [2] karsilama sutunundaki komsu bolumler (kategori cubugu, vitrin, bayrak
 *       izgarasi, ornek sorular) hangi kapta duruyor?
 *   [3] TUM setInterval envanteri (baglamiyla) — ES'te hala tik atan yazicilar
 *   [4] sb-cat-hdr (kategori akordeonu) nerede ve kim olusturuyor
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$src = @file_get_contents(__DIR__.'/index.php');
if ($src===false) exit("index.php okunamadi\n");
echo "dump-welcome-struct BASLADI (index ".number_format(strlen($src))." B)\n";

function win($src,$needle,$pre,$post,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0; $n=0;
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        $chunk=substr($src,$s,$pre+$post+strlen($needle));
        echo "[@".$p."]\n".preg_replace('/[ \t]+/',' ',$chunk)."\n────\n";
        $off=$p+strlen($needle); $n++;
    }
    if(!$n) echo "(hic yok)\n";
    $tot=substr_count($src,$needle);
    if($tot>$max) echo "(toplam $tot, ilk $max gosterildi)\n";
}

/* [1] wcat-grid olusum/yerlesim noktalari */
win($src,'wcat-grid',260,260,'[1] wcat-grid gecisleri',8);

/* [2] komsu bolumler / olasi sarmalayicilar */
foreach (['co-grid','ch-vtr-sec','vst-home','Beispiel-Fragen','ornek-sorular','quick-q','welcome','#wel','pnl-chat'] as $k)
    win($src,$k,150,150,'[2] komsu/kap: '.$k,3);

/* [3] setInterval envanteri — TAMAMI, baglamli */
echo "\n──────── [3] setInterval ENVANTERI ────────\n";
$off=0; $i=0;
while(($p=strpos($src,'setInterval(',$off))!==false){
    $s=max(0,$p-160);
    echo "[#".(++$i)." @".$p."] ".preg_replace('/\s+/',' ',substr($src,$s,160+200))."\n────\n";
    $off=$p+12;
}
echo "toplam setInterval: $i\n";

/* [4] sb-cat-hdr olusumu */
win($src,'sb-cat-hdr',300,300,'[4] sb-cat-hdr (kategori akordeonu)',4);

/* [5] guncel stabilite katmani dogrulamasi */
echo "\n──────── [5] marker kontrol ────────\n";
foreach (['CH_UI_STABILITY','CH_STAB_FINAL','CH_STAB_FINAL2','CH_SBPROBE3','CH_CLICKFIX','CH_UI_STAB2'] as $m)
    echo str_pad($m,18).": ".(strpos($src,$m)!==false?"VAR":"yok")."\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
