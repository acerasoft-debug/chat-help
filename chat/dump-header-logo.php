<?php
/**
 * ChatHelp — dump-header-logo (SADECE OKUR) — favicon linkleri, <title>,
 *  ve ust bar'daki marka/logo metnini bulmak icin (ikon+baslik degisimi
 *  icin dogru anchor'lari tespit etmek). HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-header-logo.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-header-logo — SADECE OKUR ===\n\n";
echo "[0] index.php boyutu: ".strlen($src)." bayt\n\n";

echo "[1] <head> ilk 1800 karakter (favicon/title icin):\n";
$p=stripos($src,'<head');
if($p!==false) echo substr($src,$p,1800)."\n"; else echo "(head bulunamadi)\n";

echo "\n[2] 'favicon' gecen yerler:\n";
$off=0;$n=0;
while(($p=stripos($src,'favicon',$off))!==false && $n<10){
  $seg=substr($src,max(0,$p-100),220); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+8;$n++;
}
if($n===0) echo "  (yok)\n";

echo "\n[3] <title> etiketi:\n";
$p=stripos($src,'<title');
if($p!==false){ $e=stripos($src,'</title>',$p); echo substr($src,$p,($e!==false?$e-$p+8:120))."\n"; }
else echo "  (yok)\n";

echo "\n[4] 'ChatHelp' metninin gectigi ilk 8 yer (marka/logo adaylari):\n";
$off=0;$n=0;
while(($p=strpos($src,'ChatHelp',$off))!==false && $n<8){
  $seg=substr($src,max(0,$p-90),200); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+8;$n++;
}
if($n===0) echo "  (yok)\n";

echo "\n[5] ust bar / header / logo class adaylari ('topbar','top-bar','navbar','header','logo','brand'):\n";
foreach(['class="topbar','class="top-bar','class="navbar','id="header','class="logo','class="brand','<header'] as $needle){
  $p=stripos($src,$needle);
  if($p!==false){ $seg=substr($src,$p,260); $seg=preg_replace('/\s+/',' ',$seg); echo "  [$needle] …".$seg."…\n"; }
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
