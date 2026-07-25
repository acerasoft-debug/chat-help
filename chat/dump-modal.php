<?php
/* ChatHelp - dump-modal (READ-ONLY) - gonderim/imza modalinin buton onclick handler'lari (free/send/home)
 *  + chPrintDoc iframe fonksiyonunun TAM govdesi + free butonun hangi yazdirma yolunu cagirdigini bul.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-modal.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false){ echo "index.php YOK\n"; return; }
echo "== dump-modal (".number_format(strlen($s))." B) ==\n\n";

/* 1) chPrintDoc iframe fonksiyonunun tam govdesi */
echo "=== [1] window.chPrintDoc (iframe yazdirma) TAM govde ===\n";
$q=strpos($s,'window.chPrintDoc=function');
if($q!==false){ echo str_replace("\r",'',substr($s,$q,1200))."\n\n"; }
else echo "  (bulunamadi)\n\n";

/* 2) 'free' / 'home' butonlarinin olusturuldugu + onclick baglandigi yerler.
      Modal en yeni surum: home butonu 'Original nach Hause' iceren blok. */
echo "=== [2] Modal buton wiring: 'free'/'home'/'send' onclick ===\n";
/* home label offset ~2334971 civari; o modalin buton kurulumunu goster */
$anchor=strpos($s,"home:{de:'✉️ Original nach Hause'");
if($anchor===false) $anchor=strpos($s,'Original nach Hause');
echo "  [modal label anchor @".($anchor!==false?$anchor:-1)."]\n";
if($anchor!==false){
  /* modalin buton kurulum kodu genelde label tanimindan SONRA gelir; genis bir pencere bas */
  echo str_replace("\r",'',substr($s,$anchor,3500))."\n\n";
}

/* 3) 'free' butonun onclick'inde hangi fonksiyon cagriliyor: chPrintDoc / printDoc / window.print / window.open */
echo "=== [3] free-print action: hangi yazdirma cagrisi? ===\n";
foreach(array('chPrintDoc','printDoc(','window.print','window.open') as $pat){
  $off=0;$n=0;
  while(($q=strpos($s,$pat,$off))!==false && $n<6){
    /* sadece 2300000+ (modal/gonderim bolgesi) olanlari goster */
    if($q>2290000 && $q<2420000){
      echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($s,($q-140>0?$q-140:0),300))."...\n\n";
      $n++;
    }
    $off=$q+strlen($pat);
    if($q>2420000) break;
  }
}

/* 4) modal buton elemanlarinin id/class'lari (ai-home vs) + onclick fonksiyon adlari */
echo "=== [4] modal buton id/onclick fonksiyon adlari (2295000-2415000) ===\n";
$seg=substr($s,2295000,120000);
if(preg_match_all('/function\s+(do[A-Z][A-Za-z]*)\s*\(/',$seg,$m)) echo "  do* fonksiyonlari: ".implode(', ',array_unique($m[1]))."\n";
if(preg_match_all('/id[=:]["\']?(ai-[a-z]+)/',$seg,$m2)) echo "  ai-* id'ler: ".implode(', ',array_unique($m2[1]))."\n";
if(preg_match_all('/onclick["\']?\s*[:=]\s*["\']?([a-zA-Z_]+)\(/',$seg,$m3)) echo "  onclick fonk: ".implode(', ',array_unique($m3[1]))."\n";

/* 5) 'wv()' app tespit fonksiyonu tam govde */
echo "\n=== [5] wv() app/WebView tespiti TAM ===\n";
$q=strpos($s,'function wv()');
if($q===false) $q=strpos($s,'wv=function');
if($q!==false) echo str_replace("\r",'',substr($s,$q,420))."\n";
else { $q=strpos($s,'Capacitor'); if($q!==false) echo "  Capacitor baglam: ...".str_replace("\r",'',substr($s,($q-120>0?$q-120:0),360))."...\n"; }

echo "\n== BITTI ==\n";
