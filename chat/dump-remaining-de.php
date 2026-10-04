<?php
/** ChatHelp — dump-remaining-de: cevrilmeyen sabit Almanca dizgiler (placeholder, foto kutusu, opsiyonlar).
 *  SADECE OKUR. SIL: rm dump-remaining-de.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,50)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=8){echo "\n──── $lbl (ilk $max) ────\n";$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ 1) PLACEHOLDER'LAR (input/textarea) ############\n";
RALL($idx,'placeholder=',-10,140,'placeholder tanimlari',25);

echo "\n\n############ 2) ANA GIRIS KUTUSU (composer) ############\n";
R($idx,'Anliegen',-160,320,'Schreiben Sie Ihr Anliegen (composer placeholder)');
R($idx,'Beschreiben Sie Ihre Situation',-120,300,'form textarea placeholder');

echo "\n\n############ 3) FOTO YUKLEME KUTUSU ############\n";
R($idx,'Daten werden automatisch',-260,420,'foto kutusu: Daten werden automatisch...');
R($idx,'Bescheid, Brief',-260,420,'foto kutusu: Bescheid, Brief, Beleg...');
RALL($idx,'übernommen',-160,260,'ubernommen gecen yerler',4);
R($idx,'openFall',-40,500,'openFall (foto akisi) — baslik/aciklama metinleri');

echo "\n\n############ 4) SELECT OPTION DEGERLERI (Ja/Nein vb.) ############\n";
echo "  (bunlar q.opts verisi — statik ceviri sozluguyle cozecegiz; ornek gecmeler)\n";
RALL($idx,'"Ja"',-30,80,'\"Ja\" opsiyonlari',6);
RALL($idx,'"Nein"',-30,80,'\"Nein\" opsiyonlari',6);
RALL($idx,"'Ja'",-30,80,"'Ja' (tek tirnak)",4);

echo "\n\n############ 5) FOTO/GENEL DIGER ALMANCA (attach) ############\n";
R($idx,'ch-attach',-40,400,'ch-attach bloku');
R($idx,'Foto',-80,200,'Foto* metinleri');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-remaining-de.php ===\n";
