<?php
/** ChatHelp — dump-pdffail (SADECE OKUR) — Elite hesapta PDF hala calismiyor:
 *  CH_CHATDOC_FIX uygulandi ama sorun devam ediyor. Kanit toplama:
 *  [1] canli card()/printDoc() GUNCEL hali (yamanin gercekten bu bicimde
 *      durdugunu dogrula — baska bir script tarafindan ezilmis olabilir mi)
 *  [2] makePDF() TAM govdesi (sadece bas kismini gormustuk — pencere/indirme
 *      mekanizmasi nerede, hata firlatabilecek bir sey var mi)
 *  [3] window.stripeCheckout TAM tanimi — "devdoc" docType'i gercekten
 *      destekliyor mu, DOC_PRICES icinde "einfach/standard/komplex" var mi
 *  [4] chPlan() diye bir fonksiyon GERCEKTEN var mi (ilk kontrolumuz buydu)
 *  [5] ust-duzey 'let plan' / 'var plan' degiskeninin TANIMI ve ilk degeri
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

echo "[0] CH_CHATDOC_FIX marker sayisi: ".substr_count($idx,'CH_CHATDOC_FIX')."\n";

echo "\n════ [1] canli card()/printDoc() ════\n";
$p=strpos($idx,'function printDoc(docText)');
echo $p!==false ? substr($idx,$p,900)."\n" : "(YOK)\n";
$p2=strpos($idx,'.ch-doccard-btn");');
echo "\n---- buton wiring cevresi ----\n";
echo $p2!==false ? substr($idx,max(0,$p2-900),1100)."\n" : "(YOK)\n";

echo "\n════ [2] makePDF() TAM govde (2500 bayt) ════\n";
$p3=strpos($idx,'function makePDF(doc,name,cc)');
echo $p3!==false ? substr($idx,$p3,4500)."\n" : "(YOK)\n";

echo "\n════ [3] window.stripeCheckout TAM tanimi ════\n";
$p4=strpos($idx,'stripeCheckout=function');
if($p4===false) $p4=strpos($idx,'function stripeCheckout');
echo $p4!==false ? substr($idx,max(0,$p4-30),2500)."\n" : "(YOK)\n";
echo "\n-- DOC_PRICES --\n";
$p5=strpos($idx,'DOC_PRICES');
echo $p5!==false ? substr($idx,max(0,$p5-30),700)."\n" : "(YOK)\n";

echo "\n════ [4] chPlan() tanimi ════\n";
$p6=strpos($idx,'chPlan=function');
if($p6===false) $p6=strpos($idx,'function chPlan');
echo $p6!==false ? substr($idx,max(0,$p6-30),400)."\n" : "(YOK — window.chPlan tanimli degil)\n";

echo "\n════ [5] ust-duzey plan degiskeni ════\n";
foreach(['let plan=','let plan =','var plan=','var plan ='] as $needle){
    $p7=strpos($idx,$needle);
    echo "'$needle' -> ".($p7!==false ? "@$p7: ".substr($idx,$p7,120) : "YOK")."\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
