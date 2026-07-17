<?php
/** ChatHelp — dump-gen-topic (SADECE OKUR) — 2 sorun:
 *  [A] Dilekcede ALICI (Empfänger/An) yok: doGenerate promptu answers'i nasil
 *      isliyor, "An:/Empfänger" talimati var mi? Kategori formlari alici
 *      (anbieter/firma/behoerde) soruyor mu?
 *  [B] Themen'e ICERIK kaydedilmiyor: ch_topics kaydi hangi alanlari tutuyor
 *      (doc/text var mi), showResult icerigi topic'e geciriyor mu?
 *  api.php + index.php. Gizli maskeli. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',$s); }
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$api=(string)@file_get_contents(__DIR__.'/api.php');
echo "=== dump-gen-topic — SADECE OKUR ===\n\n";
function occ($lbl,$s,$n,$pre=90,$len=340,$max=4){
  echo "[$lbl] '$n' (".substr_count($s,$n)."):\n"; $o=0;$c=0;
  while(($p=strpos($s,$n,$o))!==false && $c<$max){ echo "  @$p …".preg_replace('/\s+/',' ',substr($s,max(0,$p-$pre),$len))."…\n"; $o=$p+strlen($n);$c++; }
  if(!$c) echo "  (yok)\n"; echo "\n";
}
echo "──── [A] doGenerate prompt + alici (api.php) ────\n";
$p=stripos($api,'function doGenerate');
if($p!==false){ $body=substr($api,$p,4600);
  /* prompt kurulan kisim: answers -> metin */
  echo "[A1] doGenerate 4600-8000 (prompt kurulumu):\n".mask(substr($api,$p+4600,3400))."\n\n";
}
occ('A2',$api,'Empfänger',80,220,5);
occ('A3',$api,'Empfaenger',80,220,3);
occ('A4',$api,'recipient',80,200,4);
occ('A5',$api,'An:',60,160,4);
echo "[A6] kategori formu alici sorusu ornekleri:\n";
occ('A6a',$idx,'anbieter',70,180,3);
occ('A6b',$idx,'behoerde',70,180,3);
occ('A6c',$idx,'empfaenger',70,180,3);

echo "──── [B] Themen icerik kaydi ────\n";
occ('B1',$idx,'ch_topics',90,300,8);
$p=strpos($idx,'CH_FALL_TOPICS');
if($p!==false) echo "[B2] CH_FALL_TOPICS topic kaydi (1600 kr):\n".preg_replace('/\s+/',' ',substr($idx,$p,1600))."\n\n";
occ('B3',$idx,'topics.push',80,320,5);
occ('B4',$idx,'.push({',60,180,10);
occ('B5',$idx,'saveTopic',80,300,4);
occ('B6',$idx,"title:",50,140,8);
echo "=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
