<?php
/** ChatHelp — dump-letterctx (SADECE OKUR) — foto analizi sonrasi baglam kaybi:
 *  Kullanici raporu: fotograf analiz ediliyor (Dokument/Absender/Datum/
 *  Aktenzeichen kutusu + 2 oneri cipi cikiyor), ama kullanici "bu belgeyi
 *  nereye gondermem gerekiyor" diye sorunca AI sanki hicbir belge analiz
 *  edilmemis gibi "hangi tur belge hazirliyoruz?" diye SIFIRDAN soruyor.
 *  [1] letter-analyzer'in analiz sonucu render + oneri cip olusturma kodu
 *  [2] cip tiklaninca ne calisiyor (hangi akisa giriyor)
 *  [3] analiz verisi (Dokument/Absender/Datum/Aktenzeichen) HISTORY'YE
 *      ekleniyor mu, yoksa yalniz ekranda mi kaliyor (asil supheli nokta)
 *  [4] chAiChatTurn / doSend hangi 'history' ile AI'ye gidiyor
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function occ($src,$needle,$pre,$len,$label,$max=4){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "[1] letter-analyzer render + oneri cipleri\n";
occ($idx,'ch-letter-analyzer',-40,200,'script id izi',2);
occ($idx,'Automatische Erkennung',-300,700,'analiz kutusu + cip render blogu',2);
occ($idx,'Widerspruch gegen Beh',-200,500,'oneri metni etrafi',2);

echo "\n[2] cip tiklama handler\n";
occ($idx,'suggestChip',-100,400,'suggestChip benzeri fonksiyon',3);
occ($idx,'analyzedDoc',-100,300,'analiz sonucu degiskeni (varsa)',4);

echo "\n[3] analiz verisi history'ye giriyor mu\n";
occ($idx,'function analyzeLetter',0,600,'analyzeLetter govdesi',1);
occ($idx,'function renderAnalysis',0,600,'renderAnalysis govdesi (varsa)',1);
occ($idx,'lastAnalysis',-80,300,'lastAnalysis degiskeni (varsa)',4);

echo "\n[4] chAiChatTurn / doSend history kaynagi\n";
occ($idx,'function chAiChatTurn',0,500,'chAiChatTurn basi',1);
occ($idx,'getHist()',-60,160,'getHist cagrilari',6);

echo "\n[5] api.php doLocation() TAM govdesi (whitelist/fallback var mi?)\n";
$api=(string)@file_get_contents(__DIR__.'/api.php');
$p=strpos($api,'function doLocation');
echo $p!==false ? substr($api,$p,1400)."\n" : "(YOK)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
