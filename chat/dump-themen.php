<?php
/** ChatHelp — dump-themen (SADECE OKUR) — "Meine Anträge -> Meine Themen" tam olsun.
 *  [1] pnl-ant HEADER HTML (baslik metni hangi elemanda) @1032400-1033500
 *  [2] "Meine Anträge" / "Anträge" gorunur metin gecen yerler (baslik/empty/stat)
 *  [3] sol menu .sb-new HTML'i + ebeveyni (Meine Themen girisi nereye)
 *  [4] ch-themen-nav gercekten eklenmis mi (statik HTML'de yok, JS enjekte eder)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,max(0,$from),$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] pnl-ant HEADER HTML ════\n";
slice($idx,1032400,1000,'pnl-ant header @1032400');

echo "\n════ [2] gorunur 'Meine Anträge' / 'Anträge' metinleri ════\n";
raw($idx,'Meine Anträge',-50,120,'Meine Anträge',8);
raw($idx,'Meine Antr',-50,90,'Meine Antr (escape)',8);
raw($idx,'keine Anträge',-40,120,'empty state',3);
raw($idx,'pnl-topbar-title',-40,140,'pnl-topbar-title',6);
raw($idx,'ant-title',-40,120,'ant-title',4);

echo "\n════ [3] .sb-new HTML + ebeveyn ════\n";
raw($idx,'class="sb-new"',-120,260,'sb-new HTML',3);
raw($idx,'sb-new"',-160,60,'sb-new anchor once',4);
raw($idx,'id="sb"',-40,200,'#sb container',3);

echo "\n════ [4] ch-themen-nav / ch-clean1 durumu ════\n";
raw($idx,'ch-themen-nav',-20,90,'ch-themen-nav (enjekte JS)',4);
raw($idx,'CH_CLEAN1',-20,120,'CH_CLEAN1',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
