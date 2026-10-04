<?php
/** ChatHelp — dump-photoflow2 (SADECE OKUR) — KRITIK: ana sayfa/chat foto
 *  gonderiminde ne ceviri/ozet geliyor ne de metin dilekcede kullaniliyor.
 *  [1] doPhoto handler (ana sayfa foto) GUNCEL tam govde — UXFIX3 uygulandi mi,
 *      kart render bozuldu mu, r.zusammenfassung/rows hala duruyor mu
 *  [2] chat attach foto akisi (doSend/attach icinde analyze_photo cagrisi)
 *  [3] OCR sonucu (_lastOcrData) form'a/dilekceye NASIL aktariliyor (prefill)
 *  [4] backend doPhoto() son hali (api.php) — API key sabiti tanimli mi,
 *      dondurdugu JSON formati (result/ocr)
 *  [5] CH_UXFIX3 marker foto kartinda gercekten var mi
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");

echo "[0] marker sayilari: CH_UXFIX3=".substr_count($idx,'CH_UXFIX3')
    ." | analyze_photo(idx)=".substr_count($idx,'analyze_photo')
    ." | doPhoto(api)=".substr_count($api,'function doPhoto')."\n";

echo "\n════ [1] doPhoto/ana-sayfa foto handler GUNCEL govde ════\n";
$p=strpos($idx,"action=analyze_photo");
if($p!==false){
    echo substr($idx,max(0,$p-500),2900)."\n";
} else echo "(analyze_photo cagrisi YOK)\n";

echo "\n════ [1b] kart sonu — UXFIX3 eklemesi gorunuyor mu ════\n";
$p2=strpos($idx,'Wie möchten Sie vorgehen');
if($p2!==false){ echo substr($idx,max(0,$p2-600),1200)."\n"; }
else echo "(UXFIX3 'Wie möchten Sie vorgehen' eklemesi YOK — kart eski halinde olabilir)\n";

echo "\n════ [2] chat attach foto (2. analyze_photo) ════\n";
$off=0;$n=0;
while(($q=strpos($idx,'analyze_photo',$off))!==false && $n<3){
    echo "[#$n @$q] ".substr($idx,max(0,$q-200),450)."\n[/end]\n";
    $off=$q+13;$n++;
}

echo "\n════ [3] OCR -> form prefill (analyze/change) ════\n";
$p3=strpos($idx,'function analyze(file)');
if($p3!==false){ echo "---- analyze(file) ----\n".substr($idx,$p3,1400)."\n"; }
$off=0;$n=0;
while(($q=strpos($idx,'_lastOcrData',$off))!==false && $n<8){
    echo "[_lastOcrData @$q] ".str_replace("\n"," ⏎ ",substr($idx,max(0,$q-60),160))."\n";
    $off=$q+12;$n++;
}
$off=0;$n=0;
while(($q=strpos($idx,"type!=='file'",$off))!==false && $n<2){
    echo "\n[change-listener @$q] ".substr($idx,max(0,$q-120),700)."\n[/end]\n";
    $off=$q+10;$n++;
}

echo "\n════ [4] backend doPhoto() son hali (api.php) ════\n";
$p4=strpos($api,'function doPhoto');
echo $p4!==false ? substr($api,$p4,1900)."\n" : "(YOK)\n";
echo "\n-- CLAUDE_KEY sabiti --\n";
$off=0;$n=0;$tot=substr_count($api,'CLAUDE_KEY');
while(($q=strpos($api,'CLAUDE_KEY',$off))!==false && $n<4){
    echo "[@$q] ".str_replace("\n"," ",substr($api,max(0,$q-40),120))."\n";
    $off=$q+10;$n++;
}
echo "(toplam CLAUDE_KEY: $tot)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
