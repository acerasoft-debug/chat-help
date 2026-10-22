<?php
/**
 * ChatHelp — dump-themen2 (SADECE OKUR) — 3 is icin teshis:
 *  [A] Sol menu: id="nav-*" listesi + Chat girisi (kaldirmak icin kesin hedef)
 *  [B] Meine Themen: depolama anahtari, ekleme akisi (neden Fälle'ye gidiyor?),
 *      tiklama handler'i (neden yeniden uretime goturuyor?), Fälle'ye aktar
 *  [C] Meine Fälle: depolama yapisi (emsal karar baglamak icin)
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-themen2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-themen2 — SADECE OKUR ===\n\nindex.php: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=110,$len=400,$max=6){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p." …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n"; echo "\n";
}

echo "───── [A] Sol menu ─────\n";
preg_match_all('/id="(nav-[a-z0-9_-]+)"/',$src,$m);
echo "[A1] nav-* id'leri: ".implode(', ',array_unique($m[1]))."\n\n";
occ('A2',$src,'nav-chat',130,420,3);
occ('A3',$src,'>Chat<',150,400,5);
occ('A4',$src,'ch-themen-nav',120,420,4);

echo "───── [B] Meine Themen ─────\n";
occ('B1',$src,'Meine Themen',130,420,5);
occ('B2',$src,'ch_themen',120,420,10);
$p=strpos($src,'function renderThemen');
if($p===false){ $p=strpos($src,'renderThemen'); }
if($p!==false){ echo "[B4] renderThemen cevresi (2400 kr @".$p."):\n".substr($src,max(0,$p-150),2400)."\n\n"; }
else echo "[B4] renderThemen yok\n\n";
occ('B5',$src,'aktar',110,360,6);
occ('B6',$src,'Themen',60,220,10);

echo "───── [C] Meine Fälle ─────\n";
occ('C1',$src,'ch_faelle',120,380,6);
occ('C2',$src,'fall-api',110,340,5);
occ('C3',$src,'function getCur',80,520,2);
occ('C4',$src,'Meine Fälle',110,340,5);
occ('C5',$src,'faelle',80,280,8);

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
