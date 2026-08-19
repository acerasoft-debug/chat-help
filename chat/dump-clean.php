<?php
/** ChatHelp — dump-clean (SADECE OKUR):
 *  A) Meine Themen: pnl-ant paneli (baslik/liste), sol menu (hamburger) yapisi,
 *     Fälle'ye ekleme mekanizmasi (fall ekle) — konu Fälle'ye aktarma icin
 *  B) Ana sayfa kartlari: "Job finden & bewerben" / Bewerbung / "Module entdecken"
 *     kartlarinin HTML'i (kaldirilacak/duzenlenecek) + sozlesme (Vertrag) girisi
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,max(0,$from),$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [A1] pnl-ant paneli (Meine Anträge -> Meine Themen) ════\n";
raw($idx,'id="pnl-ant"',-40,200,'pnl-ant',3);
raw($idx,'ant-stats',-60,160,'ant-stats',3);
raw($idx,'Meine Anträge',-60,140,'Meine Anträge metin',6);
raw($idx,'function renderAnt',0,220,'renderAnt',2);
raw($idx,'loadAnt',-30,120,'loadAnt',4);

echo "\n════ [A2] sol menu (hamburger) yapisi ════\n";
raw($idx,'sidebar',-40,120,'sidebar',4);
raw($idx,'sb-new',-40,140,'sb-new (yeni sohbet)',3);
raw($idx,'id="sidebar"',-30,150,'sidebar id',2);
raw($idx,'sb-menu',-30,120,'sb-menu',4);
raw($idx,'burger',-30,110,'burger/hamburger',4);

echo "\n════ [A3] Fälle ekleme (konu -> Fälle aktarma) ════\n";
raw($idx,'function addFall',0,200,'addFall',2);
raw($idx,'saveFall',-20,120,'saveFall',4);
raw($idx,'falls(',-20,90,'falls()',5);
raw($idx,'fall_chat',-30,100,'fall_chat',3);

echo "\n════ [B1] ana sayfa kartlari ════\n";
raw($idx,'Job finden',-120,220,'Job finden karti',3);
raw($idx,'Bewerben',-100,180,'Bewerben',4);
raw($idx,'Module entdecken',-120,200,'Module entdecken karti',3);
raw($idx,'Brief oder Bescheid',-120,200,'Foto karti',2);
raw($idx,'Dokument erstellen',-120,200,'Doc-Dev karti',3);

echo "\n════ [B2] sozlesme (Vertrag) girisi ════\n";
raw($idx,'Vertrag Studio',-80,160,'Vertrag Studio',4);
raw($idx,'nav-vst',-40,120,'nav-vst',4);
raw($idx,'openVst',-30,110,'openVst',4);
raw($idx,'chVertrag',-30,110,'chVertrag',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
