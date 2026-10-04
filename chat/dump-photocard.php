<?php
/** ChatHelp — dump-photocard (SADECE OKUR) — foto-analiz sonuc kartinin
 *  DEVAMI (onceki dump "// Extracted da..." ile kesildi). Bu kartin TAM
 *  html uretimini, oneri cip(ler)inin nereden geldigini (sabit mi, r.doc'a
 *  gore mi) ve zusammenfassung'un gosterilip gosterilmedigini gorelim.
 *  Ayrica: analiz sonrasi setHist()/getHist() ile baglanti var mi (KESIN
 *  YOK teyidi icin genis pencere). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

$p=strpos($idx,"Extracted da");
if($p!==false){
    echo "──── karti uretimi (Extracted data'dan itibaren 3500 bayt) ────\n";
    echo substr($idx,$p-20,3500)."\n";
} else echo "(YOK)\n";

echo "\n════ addMsg tanimi (kisa) ════\n";
$p2=strpos($idx,'function addMsg');
echo $p2!==false ? substr($idx,$p2,500)."\n" : "(YOK)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
