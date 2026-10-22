<?php
/** ChatHelp — dump-trvize-pdf (SADECE OKUR) — TR vize belgeleri neden PDF
 *  yerine .doc/ekran cikiyor? TR vize studio print akisi + chPrintDoc + msword
 *  /octet-stream mime + blob/download izleri. HICBIR SEY YAZMAZ. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-trvize-pdf — SADECE OKUR ===\n\nindex.php: ".strlen($s)." B\n\n";
function occ($l,$s,$n,$pre=110,$len=360,$max=5){
  echo "[$l] '$n' (".substr_count($s,$n)."):\n"; $o=0;$c=0;
  while(($p=strpos($s,$n,$o))!==false && $c<$max){ echo "  @$p …".preg_replace('/\s+/',' ',substr($s,max(0,$p-$pre),$len))."…\n"; $o=$p+strlen($n);$c++; }
  if(!$c) echo "  (yok)\n"; echo "\n";
}
echo "──── TR vize studio print akisi ────\n";
occ('1',$s,'CH_VISA_STUDIO',90,260,3);
occ('2',$s,'buildPages',90,300,5);
occ('3',$s,'application/msword',120,260,6);
occ('4',$s,'msword',80,180,8);
occ('5',$s,'.doc"',60,160,8);
occ('6',$s,"'.doc'",60,160,8);
occ('7',$s,'octet-stream',100,220,6);
occ('8',$s,'vsf-',60,140,10);
echo "──── chPrintDoc tanimlari (TR vize hangisini kullaniyor) ────\n";
occ('9',$s,'window.chPrintDoc=',60,220,6);
occ('10',$s,'CH_TRVIZE_AI2',90,320,4);
/* TR vize print fonksiyonunun govdesi */
$p=strpos($s,'CH_VISA_STUDIO');
if($p!==false){ $q=strpos($s,'function open',$p); if($q!==false) echo "[11] visa-studio open() (900 kr):\n".preg_replace('/\s+/',' ',substr($s,$q,900))."\n\n"; }
echo "=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
