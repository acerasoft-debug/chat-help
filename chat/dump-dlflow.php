<?php
/** ChatHelp — dump-dlflow (SADECE OKUR) — 1-free-dilekce kotasi icin indirme/
 *  odeme akisini haritala. Amac: free (giris yapmis) kullaniciya 1 belge ver,
 *  sonra direkt odeme. Kota email+IP basina, SERVER-SIDE (auth.php free_claim).
 *  [1] chDlGate CAGRI yerleri (if(!chDlGate()) ... nasil gate ediliyor)
 *  [2] makePDF tanimi + cagrilari (indirme gercekte nerede tetikleniyor)
 *  [3] devdoc/tek-belge odeme tetigi (stripeCheckout(..,"devdoc",..))
 *  [4] stripeCheckout fonksiyon tanimi (imza)
 *  [5] MSG.plan / MSG.login (gate mesajlari) — free mesaji ekleyecegiz
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] chDlGate cagri yerleri ════\n";
raw($idx,'chDlGate(',40,90,'chDlGate() cagrilari',15);

echo "\n════ [2] makePDF tanimi + cagrilari ════\n";
$p=strpos($idx,'function makePDF');
echo $p!==false ? "-- tanim @$p --\n".substr($idx,$p,360)."\n" : "(makePDF tanimi YOK)\n";
raw($idx,'makePDF(',30,80,'makePDF() cagrilari',12);

echo "\n════ [3] devdoc / tek-belge odeme tetigi ════\n";
raw($idx,'devdoc',60,110,'devdoc',8);
raw($idx,'chPrintDoc',30,90,'chPrintDoc',6);

echo "\n════ [4] stripeCheckout tanimi + cagrilari ════\n";
$p=strpos($idx,'function stripeCheckout');
echo $p!==false ? "-- tanim @$p --\n".substr($idx,$p,420)."\n" : "(stripeCheckout tanimi YOK)\n";
raw($idx,'stripeCheckout(',20,80,'stripeCheckout() cagrilari',10);

echo "\n════ [5] gate mesajlari (MSG.plan / MSG.login) ════\n";
$p=strpos($idx,'window.chDlGate');
echo $p!==false ? "-- chDlGate cevresi @$p --\n".substr($idx,max(0,$p-40),700)."\n" : "(chDlGate YOK)\n";
raw($idx,'m.login',10,60,'m.login kullanimi',4);
raw($idx,'m.plan',10,60,'m.plan kullanimi',4);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
