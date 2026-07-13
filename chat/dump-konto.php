<?php
/** ChatHelp — dump-konto (SADECE OKUR) — Konto paneline "Zahlungsmethode/Abo
 *  verwalten" butonu koyabilmek icin:
 *  [1] pnl-konto bolgesi (buton nereye — plan gosterimi / Abmelden yakini)
 *  [2] authed fetch JWT'yi nasil gonderiyor (Authorization: Bearer ch_token)
 *  [3] konto icinde plan/abo/Abmelden/logout capalari
 *  [4] MSG i18n / dil anahtari (buton metnini cevirebilmek icin)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function raw($src,$needle,$pre,$len,$label,$max=3){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p]\n".substr($src,max(0,$p-$pre),$len)."\n[/end]\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; if($tot>$max) echo "(toplam $tot)\n";
}

echo "════ [1] pnl-konto bolgesi ════\n";
raw($idx,'pnl-konto',60,1600,'pnl-konto',2);

echo "\n════ [2] authed fetch — JWT gonderimi ════\n";
raw($idx,'Bearer',40,140,'Bearer token',5);
raw($idx,"getItem('ch_token')",30,110,"ch_token okuma",5);
raw($idx,'getItem("ch_token")',30,110,'ch_token okuma (cift tirnak)',5);

echo "\n════ [3] konto ici plan/abo/Abmelden ════\n";
foreach(['Abmelden','Mein Konto','Ihr Paket','aktueller Plan','Abonnement','tb-auth-label','plan-badge','ch-authclean'] as $nd){
    $c=substr_count($idx,$nd);
    if($c>0){ $p=strpos($idx,$nd); echo "  '$nd': $c kez, ilk@$p: ".str_replace("\n"," ",substr($idx,max(0,$p-50),120))."\n"; }
    else echo "  '$nd': YOK\n";
}

echo "\n════ [4] MSG i18n / dil ════\n";
raw($idx,'var MSG',0,300,'MSG tanimi',1);
raw($idx,'ch_uilang',20,90,'ch_uilang',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
