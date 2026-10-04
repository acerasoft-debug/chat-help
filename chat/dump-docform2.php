<?php
/** ChatHelp — dump-docform2: ANA dilekce render'inin fonksiyon imzasi + soru dongusu basi.
 *  Anchor: _pf[q.k] (ana render'a ozgu). SADECE OKUR. SIL: rm dump-docform2.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,60)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}

echo "############ 1) ANA RENDER: fonksiyon imzasi -> soru dongusu (dk/doc/forEach) ############\n";
R($idx,'_pf[q.k]',-1500,1900,'_pf[q.k] etrafinda ana render (imza + forEach + input)');

echo "\n\n############ 2) forEach dongu ifadesi (hangi dizi iterasyonu) ############\n";
R($idx,'.forEach(q=>{',-400,120,'ilk .forEach(q=>{ (soru dizisi)');
R($idx,'_pf={',-260,200,'_pf objesi oncesi (doc.q / questions degiskeni)');

echo "\n\n############ 3) fonksiyon adi (bu render nasil cagriliyor) ############\n";
R($idx,'const doc=DOCS[dk]||DOCS._default;const c=CC_DATA',-140,180,'render girisi (doc=DOCS[dk]) + fonksiyon adi geriden');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-docform2.php ===\n";
