<?php
/** ChatHelp — dump-docform: dilekce formu render fonksiyonu (muhatap alani enjekte etmek icin).
 *  SADECE OKUR. SIL: rm dump-docform.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function W($s,$from,$len,$lbl){echo "\n──── $lbl [@$from +$len] ────\n".substr($s,$from,$len)."\n";}
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,50)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=6){echo "\n──── $lbl (ilk $max) ────\n";$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ 1) FORM RENDER FONKSIYONU (imza + basi) ############\n";
echo "  (Dokument generieren butonu ~@721300; fonksiyon basi bundan once)\n";
W($idx,717600,1500,'render fonksiyon govdesi (imza icin geriden)');

echo "\n\n############ 2) RENDER FONKSIYON ADI (nasil cagriliyor) ############\n";
RALL($idx,'.forEach(q=>',-160,120,'soru dongusu (q iterasyonu)',4);
R($idx,'doc.q',-200,200,'doc.q okunmasi');
R($idx,'.q||[]',-160,160,'q||[] fallback');
RALL($idx,'function showDoc',0,120,'showDoc*',3);
RALL($idx,'function openDoc',0,120,'openDoc*',3);
RALL($idx,'function startDoc',0,120,'startDoc*',3);
RALL($idx,'function renderDoc',0,120,'renderDoc*',3);
RALL($idx,'function pickDoc',0,120,'pickDoc*',3);

echo "\n\n############ 3) 'body' ve submit toplama (data-k) ############\n";
R($idx,'const body=',-40,160,'body olusturma');
R($idx,"forEach(el=>ans[el.dataset.k]",-260,340,'submit: data-k toplama');

echo "\n\n############ 4) DOC KATEGORI/TIP (bewerbung/vize/vertrag ayirt etmek icin) ############\n";
R($idx,'const doc=DOCS[dk]',-120,300,'doc=DOCS[dk] baglami (dk burada)');
R($idx,'cat:',-10,120,'ornek doc cat alani');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-docform.php ===\n";
