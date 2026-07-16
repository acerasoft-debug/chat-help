<?php
/**
 * ChatHelp — dump-vize-layout (SADECE OKUR) — vize duzenlemesi icin teshis:
 *  [A] Ana sayfadaki vize kartlari: ulke-ulke kartlar nereden geliyor
 *      (wcat-grid icerigi + showCatDocs cagrilari + 'Vize'/'Visum' kart adlari)
 *  [B] Vize Asistani "Hazirla" akisi: __vzGen ne yapiyor (form var mi, dogrudan
 *      uretim mi)
 *  [C] chVizeAsistan nereden aciliyor (ana sayfa baglantisi var mi)
 *  [D] Turkiye Vize Modulu durumu (tr vize kartlari/kategorileri)
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-vize-layout.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-vize-layout — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=100,$len=380,$max=8){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

echo "───── [A] Ana sayfa kartlari ─────\n\n";
echo "[A1] wcat-grid TAM icerik (ilk gecis, 3000 karakter):\n";
$p=strpos($src,'<div class="wcat-grid">');
if($p!==false) echo substr($src,$p,3000)."\n"; else echo "(yok)\n";
echo "\n";
occ('A2',$src,'showCatDocs(',60,200,14);
occ('A3',$src,'Vize',60,220,12);
occ('A4',$src,'Visum',60,220,8);

echo "───── [B] Hazirla akisi ─────\n\n";
occ('B1',$src,'window.__vzGen',20,1200,2);

echo "───── [C] Vize Asistani acilis noktalari ─────\n\n";
occ('C1',$src,'chVizeAsistan(',80,250,8);

echo "───── [D] TR Vize Modulu ─────\n\n";
occ('D1',$src,'Türkiye Vize',80,300,5);
occ('D2',$src,'trvize',60,250,6);
occ('D3',$src,'tr_vize',60,250,6);

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
