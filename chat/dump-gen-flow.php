<?php
/** ChatHelp — dump-gen-flow: api.php belge uretim akisi (anonimlestirme, cevap yerlestirme, prompt).
 *  Amac: (A) girilen cevaplar neden [PLACEHOLDER] kaliyor, (B) uzunluk/kanun promptu, (C) muhatap alani.
 *  SADECE OKUR. SIL: rm dump-gen-flow.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,50)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function RALL($s,$needle,$b,$l,$lbl,$max=6){echo "\n──── $lbl (ilk $max) ────\n";$off=0;$n=0;while(($p=strpos($s,$needle,$off))!==false&&$n<$max){echo "\n[@$p #".($n+1)."]\n".substr($s,max(0,$p-$b),$l)."\n";$off=$p+strlen($needle);$n++;}if($n===0)echo "(BULUNAMADI)\n";}

echo "############ A) ANSWERS -> PROMPT (cevaplar nasil metne donuyor) ############\n";
R($api,'answersText',-400,700,'answersText olusturma');
RALL($api,'answersText',-30,120,'answersText tum kullanimlari',8);
R($api,'action=generate',-40,300,'generate action girisi');
R($api,"'generate'",-40,400,"generate action (tek tirnak)");

echo "\n\n############ B) ANONIMLESTIRME + GERI YERLESTIRME (neden [KUNDENNUMMER] kaliyor) ############\n";
foreach(['anonym','placeholder','\$ph','ph[','str_replace','preg_replace','PLZ_ORT','KUNDENNUMMER','substitute','deanon','reinsert'] as $k){
  $p=strpos($api,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($api,'$ph',-40,600,'$ph placeholder haritasi (tanim)');
RALL($api,'str_replace',-120,220,'str_replace (geri yerlestirme) yerleri',10);
R($api,'PLZ_ORT',-200,400,'PLZ_ORT baglami (ph map)');

echo "\n\n############ C) SISTEM PROMPTU / RULES (uzunluk + kanun maddeleri) ############\n";
R($api,'RULES',-200,1400,'RULES blogu TAM');
R($api,'You are',-40,700,'sistem promptu basi');
R($api,'CH_LAUNCH_PROMPT',-900,1100,'benim ekledigim kurallar (uzunlugu bunlar artirdi mi)');

echo "\n\n############ D) MUHATAP (empfaenger/behoerde) ALANI ############\n";
foreach(['empfaenger','behoerde','Empfänger','recipient','anschrift','Anschrift','adresse_empf'] as $k){
  $p=strpos($api,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($api,'empfaenger',-100,300,'empfaenger prompt icinde');

echo "\n\n############ E) DOC TIER (basit/karmasik ayrimi var mi) ############\n";
foreach(['tier','einfach','komplex','standard','docType','simple'] as $k){
  $p=strpos($api,$k); echo "  '$k' -> ".($p===false?'yok':'VAR @'.$p)."\n";
}
R($api,'tier',-60,300,'tier api.php icinde (prompt uzunlugu ayarlanabilir mi)');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-gen-flow.php ===\n";
