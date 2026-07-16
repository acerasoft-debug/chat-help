<?php
/**
 * ChatHelp — dump-vzgen-full (SADECE OKUR) — Vize Asistani "Hazirla" akisinin
 *  TAM govdesi: window.__vzGen fonksiyonu (API cagrisi + PDF uretimi nasil),
 *  prompt kurucu, VZ durum nesnesi. "Hazirla" oncesi kisisel-bilgi formu ve
 *  belgeler hazirlaninca "Prüfung" (son kontrol) adimi eklemek icin gerekli
 *  KESIN anchor'lar. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-vzgen-full.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-vzgen-full — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

echo "[1] 'window.__vzGen=' tanimindan itibaren 3200 karakter:\n";
$p=strpos($src,'window.__vzGen=');
if($p!==false) echo substr($src,$p,3200)."\n"; else echo "(yok)\n";

echo "\n[2] Vize Asistani modulunde VZ durum nesnesi (var VZ= ... 400 kr):\n";
$p=strpos($src,'var VZ=');
if($p!==false) echo substr($src,$p,400)."\n"; else echo "(yok)\n";

echo "\n[3] STEP===4 cizimi ('Bitti' dugmesinin tam satiri, 500 kr):\n";
$p=strpos($src,"window.__vzClose()\">Bitti</button>");
if($p!==false) echo substr($src,max(0,$p-380),700)."\n"; else echo "(yok — 'Bitti' butonu bulunamadi)\n";

echo "\n[4] Vize modulunun api cagrisi ('vize' veya 'vz' action'li fetch, ilk 2):\n";
$off=0;$n=0;
while(($p=strpos($src,'fetch(',$off))!==false && $n<40){
  $seg=substr($src,$p,300);
  if(strpos($seg,'api.php')!==false && (strpos(substr($src,max(0,$p-2500),3000),'__vzGen')!==false)){
    $seg2=preg_replace('/\s+/',' ',substr($src,max(0,$p-150),600));
    echo "  …".$seg2."…\n"; $n+=20;
  }
  $off=$p+6;$n++;
}
if($n<20) echo "  (vzGen yakininda api.php fetch bulunamadi — [1] ciktisinda gorunecektir)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
