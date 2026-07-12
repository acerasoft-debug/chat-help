<?php
/** ChatHelp — dump-checkout (SADECE OKUR) — checkout akisinin ham baytlari
 *  F2-5 premium plan sayfasindaki "sec" dugmeleri dogru fonksiyonu cagirsin:
 *  stripeCheckout / makeCheckout tanimlari, plan panelinin acildigi yer
 *  (gP('konto')?), ve yeni sayfayi nasil acabilecegimiz (panel/gP sistemi). */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
if($src==='') exit("index.php okunamadi\n");

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

/* checkout fonksiyon tanimlari */
raw($src,'function stripeCheckout',0,420,'stripeCheckout tanimi',2);
raw($src,'function makeCheckout',0,420,'makeCheckout tanimi',2);
raw($src,'stripe-checkout.php',-160,260,'stripe-checkout.php cagrisi',3);

/* panel/gP sistemi: yeni panel nasil aciliyor */
raw($src,'function gP(',0,300,'gP panel switcher',1);
raw($src,'id="pnl-konto"',-40,120,'konto paneli',2);
raw($src,'id="pnl-',-5,60,'mevcut panel idleri',20);

/* plan panelini acan tetik (Konto icindeki "Plan/Upgrade" dugmesi) */
raw($src,'chPlans',-60,140,'chPlans / plan paneli acma',6);
raw($src,'renderPlans',-60,180,'renderPlans',4);
raw($src,'chp-grid',-120,120,'chp-grid (fiyat kart konteyneri)',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
