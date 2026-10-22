<?php
/**
 * ChatHelp — dump-theme-white-check (SADECE OKUR) — kullanici "beyaz tema"yi
 *  denedi ve "kartlar degil, arka plan beyaz olacakti" dedi (yani beyaz
 *  secilince beklenen sonuc olmadi). [data-chtheme="white"] CSS blogunun
 *  TAM icerigini + body/app arka plan degiskenlerini (--bg) nerede
 *  kullanildigini gormek icin. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-theme-white-check.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-theme-white-check — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=20,$len=1400,$max=3){
  echo "[$label] '$needle':\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len);
    echo "  ---\n  ".$seg."\n";
    $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

occ('1-white-block',$src,'[data-chtheme="white"]',10,1600,2);
occ('2-navy-block',$src,'[data-chtheme="navy"]',10,900,1);
occ('3-root-vars',$src,':root{',0,900,1);
occ('4-body-bg',$src,'body{background',0,300,2);
occ('5-app-bg',$src,'#app{',0,300,2);

echo "[6] '--bg' gecen ilk 10 CSS kural basi (kisa):\n";
$off=0;$n=0;
while(($p=strpos($src,'--bg',$off))!==false && $n<12){
  $seg=substr($src,max(0,$p-50),110); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+4;$n++;
}
if($n===0) echo "  (yok)\n";

echo "\n[7] 'data-chtheme' gecen TUM yerler (kac tane, ne icin):\n";
echo "  toplam sayi: ".substr_count($src,'data-chtheme')."\n";
$off=0;$n=0;
while(($p=strpos($src,'data-chtheme',$off))!==false && $n<20){
  $seg=substr($src,max(0,$p-40),140); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+12;$n++;
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
