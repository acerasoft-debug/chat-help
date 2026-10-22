<?php
/**
 * ChatHelp — dump-theme-system (SADECE OKUR) — "sisteme gore otomatik beyaz
 *  tema" icin mevcut tema altyapisini tam gormek:
 *  [1] ch_theme localStorage anahtari okuma/yazma baglamlari
 *  [2] chtheme-btn / data-chtheme secenekleri (kac tema var, isimleri)
 *  [3] data-chtheme="white"/"navy"/"anthracite" CSS degisken tanimlari
 *  [4] prefers-color-scheme zaten kullaniliyor mu (media query)
 *  [5] Konto'daki antrasit temanin ayri bir mekanizmasi var mi
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-theme-system.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-theme-system — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

function occ($label,$hay,$needle,$pre,$len,$max=8){
  echo "── $label ('$needle') [toplam ".substr_count($hay,$needle)."] ──\n";
  $off=0;$n=0;
  while(($p=strpos($hay,$needle,$off))!==false && $n<$max){
    $seg=substr($hay,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if(!$n) echo "  (yok)\n";
  echo "\n";
}
function bodyOf($s,$decl,$cap=1500){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)\n";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,180)."\n";
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)\n":"\n");
}

echo "[1] ch_theme localStorage baglamlari:\n";
occ('ch_theme',$src,'ch_theme',40,150,10);

echo "[2] chtheme-btn / data-t secenekleri:\n";
occ('chtheme-btn',$src,'chtheme-btn',40,160,10);
occ("data-t=",$src,'data-t="',20,60,10);

echo "[3] data-chtheme CSS tanimlari (tum degerler):\n";
occ('data-chtheme',$src,'data-chtheme=',15,140,15);

echo "[4] prefers-color-scheme kullanimi (zaten var mi):\n";
occ('prefers-color-scheme',$src,'prefers-color-scheme',30,160,6);

echo "[5] Tema uygulama fonksiyonu TAM govde (applyTheme/setTheme vb.):\n";
foreach(['function applyTheme','function setTheme','function chTheme'] as $fn){
  $r = bodyOf($src,$fn,1400);
  if(strpos($r,'YOK:')===false){ echo "-- $fn --\n$r\n"; }
}

echo "[6] Ana renk CSS degiskenleri (:root icindeki --s2/--s3/--bg vb ilk tanim):\n";
$p=strpos($src,':root{');
if($p!==false){ echo substr($src,$p,600)."\n"; } else echo "(:root{ bulunamadi)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
