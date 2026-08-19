<?php
/**
 * ChatHelp — dump-ibanclean — api.php'deki "bos etiket satirini sil" blogunun
 *  TAM hali. Amac: 'IBAN:' gibi degeri bos satirlar siliniyor; bunlari silmek
 *  yerine 'IBAN: ______' seklinde TAMAMLAMAK icin birebir dogru yamayi yazmak.
 * KULLANIM: pull2.php?key=...&files=dump-ibanclean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$f=__DIR__.'/api.php';
$s=(string)@file_get_contents($f);
if($s===''){ echo "api.php okunamadi\n"; exit; }
echo "== dump-ibanclean (api.php ".strlen($s)." B, ".date('Y-m-d H:i',filemtime($f)).") ==\n\n";

echo "=== [1] Temizlik blogu (regex etrafi, genis) ===\n";
$p=strpos($s,'Steuer-?ID');
if($p===false){ echo "  regex bulunamadi\n"; }
else{
  echo substr($s,max(0,$p-1600),3200)."\n";
}

echo "\n=== [2] \$chOut kullanimlari (cikti dizisi nasil dolduruluyor) ===\n";
$off=0;$n=0;
while(($q=strpos($s,'$chOut',$off))!==false && $n<14){
  echo "  [@$q] ".preg_replace('/\s+/',' ',substr($s,max(0,$q-60),150))."\n";
  $off=$q+6;$n++;
}
if($n===0) echo "  yok\n";

echo "\n=== [3] Fonksiyon adi (bu blok hangi fonksiyonda) ===\n";
if($p!==false){
  $head=substr($s,0,$p);
  $fp=strrpos($head,'function ');
  if($fp!==false) echo "  ".preg_replace('/\s+/',' ',substr($s,$fp,120))."\n";
  else echo "  bulunamadi\n";
}

echo "\n=== [4] Sayimlar ===\n";
foreach(['CH_CLEANLINE','CH_DOCQUAL','chSanitize','$chT','$chOut','continue;'] as $k){
  echo "  ".str_pad($k,16)." x".substr_count($s,$k)."\n";
}
echo "\n== BITTI ==\n";
