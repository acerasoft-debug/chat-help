<?php
/** ChatHelp — dump-airoute (SADECE OKUR) — AI saglayici yonlendirme haritasi
 *  F2-9 icin: sohbet hangi modele gidiyor, plan bazli ayrim var mi, PDF/belge
 *  istegi nasil algilaniyor. api.php (aichat) + index.php (pickProvider) +
 *  fall-api.php (chAI1 DeepSeek / chAI2 Claude) ham baytlari. TAMAMINI gonder. */
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
raw($idx,'function pickProvider',0,360,'pickProvider (frontend model secimi)',2);
raw($idx,'action=aichat',-40,260,'aichat cagrisi (body: provider/plan/pdf?)',3);
raw($idx,'pickProvider(',-20,80,'pickProvider cagrilari',6);

$api=(string)@file_get_contents("$D/api.php");
echo "\n════════════════ api.php ════════════════\n";
raw($api,'doAiChat',-40,120,'doAiChat giris',2);
raw($api,'provider',-30,120,'provider kullanimi',8);
raw($api,'deepseek',-40,120,'deepseek cagrisi',4);
raw($api,'DEEPSEEK',-40,100,'DEEPSEEK sabiti',4);
raw($api,'claude',-40,120,'claude cagrisi',6);
raw($api,'anthropic',-40,100,'anthropic endpoint',4);
raw($api,'[[DOC]]',-80,160,'[[DOC]] PDF/belge tetigi',4);
raw($api,'CH_ASKFIRST',-30,140,'askfirst kapisi',2);

$fall=(string)@file_get_contents("$D/fall-api.php");
echo "\n════════════════ fall-api.php ════════════════\n";
raw($fall,'function chAI1',0,200,'chAI1 (DeepSeek)',1);
raw($fall,'function chAI2',0,200,'chAI2 (Claude)',1);
raw($fall,'claude-',-30,80,'claude model id',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
