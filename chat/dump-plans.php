<?php
/** ChatHelp — dump-plans (SADECE OKUR) — plan/Stripe akisi: fiyat modali (#pm),
 *  plan butonlari + onclick, Stripe checkout cagrisi (stripe-checkout.php),
 *  sozlesme "kaufen" butonu (gBuy). Hassas maskeli. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk_live_|sk_test_|whsec_|price_)[A-Za-z0-9_]+/','$1***',$s); }
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$sc=(string)@file_get_contents(__DIR__.'/stripe-checkout.php');
function raw($src,$needle,$pre,$len,$label,$max=8){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",mask(substr($src,max(0,$p-$pre),$len)))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}
echo "════════ [1] index.php — plan/fiyat UI + stripe cagrisi ════════\n";
raw($idx,'stripe-checkout',-120,180,'stripe-checkout cagrisi',8);
raw($idx,'function openPlans',-20,200,'openPlans',3);
raw($idx,'function showPlans',-20,200,'showPlans',3);
raw($idx,'function cPM',-20,120,'cPM (pricing modal)',2);
raw($idx,'openPM',-40,120,'openPM',6);
raw($idx,'data-plan',-60,120,'data-plan butonlari',8);
raw($idx,'checkout',-60,120,'checkout genel',12);
raw($idx,'in Kürze',-120,60,'in Kurze (placeholder buton)',6);
raw($idx,'gBuy',-40,140,'gBuy (sozlesme kaufen)',6);
raw($idx,'chBuy',-40,140,'chBuy',8);
raw($idx,'plan_select',-40,120,'plan_select',5);

echo "\n════════ [2] stripe-checkout.php (".strlen($sc)." bayt) ════════\n";
if($sc===''){ echo "stripe-checkout.php OKUNAMADI/YOK\n"; }
else {
  raw($sc,'price',-40,120,'price / plan tanimlari',14);
  raw($sc,'mode',-30,90,'mode (subscription/payment)',6);
  raw($sc,'$_',-20,80,'girdi parametreleri',12);
  raw($sc,'checkout/sessions',-60,120,'session olusturma',3);
}
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
