<?php
/** ChatHelp — dump-chatdoc2 (SADECE OKUR) — onceki dumpun devami:
 *  [1] CH_AICHAT_DOCPDF scriptinin TAMAMI (PDF butonu onclick dahil)
 *  [2] window.jspdf HERHANGI BIR YERDE set ediliyor mu (yoksa hayalet
 *      kontrol mu — bu PDF'in calismama sebebi olabilir)
 *  [3] makePDF() fonksiyon govdesinin TAMAMI (jsPDF kutuphanesi mi,
 *      window.print() mi kullaniyor)
 *  [4] doAiChat() sisteminin DEVAMI (yer-tutucu talimati, odeme/plan
 *      kontrolu var mi, [[DOC]] format talimati)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$api=(string)@file_get_contents(__DIR__.'/api.php');
if($idx==='') exit("index.php okunamadi\n");

$p=strpos($idx,'id="ch-aichat-docpdf"');
echo "════ [1] CH_AICHAT_DOCPDF TAM script ════\n";
echo $p!==false ? substr($idx,$p,4500)."\n" : "(YOK)\n";

echo "\n════ [2] window.jspdf kullanimlari (TUMU) ════\n";
$off=0;$n=0;$tot=substr_count($idx,'jspdf');
while(($q=strpos($idx,'jspdf',$off))!==false && $n<15){
    echo "[@$q] ".substr($idx,max(0,$q-80),200)."\n[/end]\n";
    $off=$q+5;$n++;
}
echo "(toplam '$tot' kucuk-harf 'jspdf' gecisi)\n";
$tot2=substr_count($idx,'jsPDF');
echo "(toplam '$tot2' 'jsPDF' - buyuk P - gecisi)\n";
if($tot2>0){
    $off=0;$n=0;
    while(($q=strpos($idx,'jsPDF',$off))!==false && $n<8){
        echo "[jsPDF @$q] ".substr($idx,max(0,$q-80),220)."\n[/end]\n";
        $off=$q+5;$n++;
    }
}

echo "\n════ [3] makePDF() fonksiyon govdesi TAM ════\n";
$p3=strpos($idx,'function makePDF(doc,name,cc)');
echo $p3!==false ? substr($idx,$p3,2200)."\n" : "(YOK)\n";

echo "\n════ [4] doAiChat() sistem promptunun DEVAMI + odeme kontrolu ════\n";
$p4=strpos($api,'PRIVACY: never ask for');
echo $p4!==false ? substr($api,$p4-50,3000)."\n" : "(YOK)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
