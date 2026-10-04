<?php
/** ChatHelp — dump-chatdoc3 (SADECE OKUR) — son parca:
 *  [1] card() fonksiyonunun DEVAMI (buton click-wiring — onceki dump
 *      "_kk=docText.length+..." ile kesildi)
 *  [2] window.chDlGate TAM tanimi (var mi, neyi kontrol ediyor, engellerken
 *      herhangi bir UI/paywall gosteriyor mu)
 *  [3] doAiChat() sisteminin geri kalani (payment/quota kontrolu var mi)
 *  [4] extract()/[[DOC]] sonucunun NEREDE cagrildigi (hangi kod card()'i tetikliyor) */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$api=(string)@file_get_contents(__DIR__.'/api.php');
if($idx==='') exit("index.php okunamadi\n");

$p=strpos($idx,'_kk=docText.length');
echo "════ [1] card() devami (buton wiring) ════\n";
echo $p!==false ? substr($idx,max(0,$p-40),1800)."\n" : "(YOK)\n";

echo "\n════ [2] window.chDlGate TAM tanimi ════\n";
$p2=strpos($idx,'chDlGate=function');
if($p2===false) $p2=strpos($idx,'function chDlGate');
echo $p2!==false ? substr($idx,max(0,$p2-30),1400)."\n" : "(YOK — baska isimde olabilir)\n";
$off=0;$n=0;$tot=substr_count($idx,'chDlGate');
echo "\n(tum 'chDlGate' gecisleri, toplam $tot)\n";
while(($q=strpos($idx,'chDlGate',$off))!==false && $n<8){
    echo "[@$q] ".substr($idx,max(0,$q-60),160)."\n[/end]\n";
    $off=$q+8;$n++;
}

echo "\n════ [3] doAiChat sonrasi (odeme/kota kontrolu) ════\n";
$p3=strpos($api,'If a specialized to');
echo $p3!==false ? substr($api,$p3,2500)."\n" : "(YOK)\n";

echo "\n════ [4] extract()/[[DOC]] sonucu nereden cagriliyor ════\n";
$p4=strpos($idx,'function extract(text)');
echo $p4!==false ? "extract() bulundu @$p4\n" : "extract() bulunamadi\n";
$off=0;$n=0;
while(($q=strpos($idx,'extract(reply)',$off))!==false && $n<4){
    echo "[@$q] ".substr($idx,max(0,$q-300),500)."\n[/end]\n";
    $off=$q+10;$n++;
}
$off=0;$n=0;
while(($q=strpos($idx,'extract(d.reply)',$off))!==false && $n<4){
    echo "[@$q] ".substr($idx,max(0,$q-300),500)."\n[/end]\n";
    $off=$q+10;$n++;
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
