<?php
/** ChatHelp — dump-makepdf (SADECE OKUR)
 *  printDoc() icinde "window.jspdf && makePDF" dalı VAR — bu, eger jsPDF
 *  kutuphanesi yukluyse HTML/CSS sablonumu tamamen atlayip JS-tabanli PDF
 *  uretiyor demek. jsPDF'in VARSAYILAN fontlari (Helvetica/Times/Courier)
 *  SADECE Latin-1/WinAnsi destekler — Turkce ğşıöü, Arapca, Kiril
 *  KARAKTERLER DOGRU BASILAMAZ. Bu dump: (a) makePDF() fonksiyonunun tam
 *  halini, (b) jsPDF kutuphanesinin nereden/ne zaman yuklendigini,
 *  (c) window.jspdf'in gercekten set edilip edilmedigini gosterir.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-makepdf.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");

echo "════════ makePDF / jsPDF TESHIS ════════\n\n";

/* ── makePDF fonksiyon govdesi ── */
echo "###### makePDF() TAM FONKSIYON ######\n";
$p = strpos($idx, 'function makePDF');
if ($p===false) echo "makePDF fonksiyonu bulunamadi (tanimsiz -> her zaman catch'e dusup HTML yoluna gecer)\n\n";
else {
    $depth=0; $b=strpos($idx,'{',$p); $i=$b; $len=strlen($idx);
    for(;$i<$len;$i++){ if($idx[$i]==='{')$depth++; elseif($idx[$i]==='}'){$depth--; if($depth===0){$i++;break;}} }
    echo substr($idx,$p,$i-$p)."\n\n";
}

/* ── jsPDF kutuphane yuklemesi ── */
echo "###### jsPDF SCRIPT/CDN YUKLEMESI ######\n";
$off=0;$n=0;
while(($p=stripos($idx,'jspdf',$off))!==false && $n<25){
    $seg=substr($idx,max(0,$p-60),140); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+5; $n++;
}
if(!$n) echo "  'jspdf' kelimesi index.php'de HIC gecmiyor!\n";

/* ── window.jspdf gercekten nerede set ediliyor ── */
echo "\n###### window.jspdf ATAMASI ######\n";
$off=0;$n=0;
while(($p=stripos($idx,'window.jspdf',$off))!==false && $n<10){
    $seg=substr($idx,max(0,$p-60),160); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+10; $n++;
}
if(!$n) echo "  window.jspdf HICBIR YERDE atanmiyor -> 'if(window.jspdf...)' HER ZAMAN false -> HTML yolu HER ZAMAN kullanilir (guvenli).\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-makepdf.php ════════\n";
