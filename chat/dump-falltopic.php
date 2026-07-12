<?php
/** ChatHelp — dump-falltopic (SADECE OKUR) — F2-10 icin harita:
 *  [1] Meine Fälle listesi nerede/nasil cizilir (fall list, pnl-fall)
 *  [2] Belge uretimi bitis noktalari (showResult + intake solve + chat [[DOC]])
 *  [3] api.php depo yardimcilari (STORE_DIR okuma/yazma, sid, action router)
 *  [4] fall-api fall olusturma (kota yiyen yer — topic bunu KULLANMAYACAK)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;

function raw($src,$needle,$pre,$len,$label,$max=3){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        echo "[@$p]\n".substr($src,$s,$len)."\n[/end]\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n";
    $tot=substr_count($src,$needle); if($tot>$max) echo "(toplam $tot)\n";
}

$idx=(string)@file_get_contents("$D/index.php");
echo "════════════════ index.php ════════════════\n";
raw($idx,'function showResult',0,500,'showResult (belge bitisi ana nokta)',1);
raw($idx,'Meine Fälle',-60,160,'Meine Fälle metni',4);
raw($idx,'fallList',-40,300,'fallList / liste cizimi',4);
raw($idx,'openFall',-30,220,'openFall tanim/cagri',3);
raw($idx,'fall_list',-40,200,'fall_list action cagrisi',3);
raw($idx,'Meine Anträge',-60,120,'Meine Antraege metni',2);
raw($idx,'function fallNew',0,260,'fallNew',1);

$api=(string)@file_get_contents("$D/api.php");
echo "\n════════════════ api.php ════════════════\n";
raw($api,'STORE_DIR',-60,160,'STORE_DIR kullanimlari',6);
raw($api,"match(",-160,700,'action router (match blogu)',1);
raw($api,'function doHistory',0,420,'doHistory (depo okuma ornegi)',1);
raw($api,'function doKeepdoc',0,420,'doKeepdoc (varsa saklama ornegi)',1);
raw($api,'$sid',-40,120,'sid kaynagi',4);

$fall=(string)@file_get_contents("$D/fall-api.php");
echo "\n════════════════ fall-api.php ════════════════\n";
raw($fall,"'fall_new'",-80,260,'fall_new (kota yiyen olusturma)',2);
raw($fall,"'fall_list'",-60,300,'fall_list',2);
raw($fall,'fall_store',-40,200,'fall depolama',3);
raw($fall,'falls/',-60,160,'falls dizini',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
