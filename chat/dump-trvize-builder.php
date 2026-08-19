<?php
/**
 * ChatHelp — dump-trvize-builder (SADECE OKUR) — "yari Turkce yari Ingilizce
 *  belge" kaynagi: TR Vize Modulu'nun sablon-birlestirme kodu. Kullanicinin
 *  Turkce yanitlarini Ingilizce kaliba HAM yapistiran uretici fonksiyonun
 *  TAM yeri + cikti yolu (makePDF mi, kendi goruntuleyicisi mi) +
 *  "PDF olarak kaydet" dugmesinin ne yaptigi. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-trvize-builder.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-trvize-builder — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=150,$len=700,$max=4){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

occ('1',$src,'Vize Niyet Dilekçesi',150,600,3);
occ('2',$src,'Dear Sir or Madam',200,800,3);
occ('3',$src,'I am currently working',250,800,3);
occ('4',$src,'COVER LETTER',150,500,3);
occ('5',$src,'PDF olarak kaydet',200,700,3);
occ('6',$src,'Belgeyi oluştur',200,700,3);
occ('7',$src,'ensure my return',250,700,3);

echo "[8] Sablon-birlestirici fonksiyon adaylari ('function build', 'function makeDoc', 'buildDoc'):\n";
foreach(['function buildDoc','function makeDoc','function docText','function renderDoc','function genDoc'] as $needle){
  $p=strpos($src,$needle);
  echo "  ".str_pad($needle,22).": ".($p!==false?("VAR @".$p):"yok")."\n";
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
