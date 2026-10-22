<?php
/**
 * ChatHelp — dump-chatpdf (SADECE OKUR) — "chatte belge istenince PDF bos
 *  ('...') cikiyor" teshisi: [[DOC]] akisi, PDF dugmesi handler'i, makePDF/
 *  chPrintDoc govde baslari, '...' yer tutucusunun kaynagi, 'PDF olarak
 *  kaydet' arac cubugunun uretildigi yer. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-chatpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-chatpdf — SADECE OKUR ===\n\nindex.php: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=120,$len=420,$max=5){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p." …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n"; echo "\n";
}

echo "───── [A] PDF arac cubugu + '...' kaynak ─────\n";
occ('A1',$src,'PDF olarak kaydet',150,460,4);
occ('A2',$src,'Metni Kopyala',150,460,4);
occ('A3',$src,"'...'",100,300,8);
occ('A4',$src,'"..."',100,300,8);

echo "───── [B] makePDF / chPrintDoc ─────\n";
$p=strpos($src,'window.makePDF');
if($p!==false) echo "[B1] window.makePDF govde (1400 kr):\n".preg_replace('/\s+/',' ',substr($src,$p,1400))."\n\n";
occ('B2',$src,'function makePDF',100,500,3);
$p=strpos($src,'window.chPrintDoc=');
$k=0;
while($p!==false && $k<4){
  echo "[B3.".$k."] window.chPrintDoc= @$p (700 kr):\n".preg_replace('/\s+/',' ',substr($src,$p,700))."\n\n";
  $p=strpos($src,'window.chPrintDoc=',$p+10); $k++;
}

echo "───── [C] chat [[DOC]] -> PDF dugmesi ─────\n";
occ('C1',$src,'[[DOC]]',120,420,5);
occ('C2',$src,'makePDF(',110,320,10);

echo "───── [D] sohbet belge dugmesi handler ─────\n";
occ('D1',$src,'PDF herunterladen',120,380,4);
occ('D2',$src,'PDF indir',120,380,4);
occ('D3',$src,'chDlGate',110,340,5);

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
