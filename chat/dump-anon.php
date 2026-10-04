<?php
/** ChatHelp — dump-anon: doGenerate TAM govde (anonimlestirme + geri-koyma + CASE DETAILS + de-anon).
 *  Amac: girilen cevaplar neden [PLACEHOLDER] kaliyor, geri-koyma haritasi nasil.
 *  SADECE OKUR. SIL: rm dump-anon.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php yok\n");
function W($s,$from,$len,$lbl){echo "\n──── $lbl [@$from +$len] ────\n".substr($s,$from,$len)."\n";}
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '".substr($needle,0,50)."')\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}

echo "############ 1) doGenerate TAM GOVDE (ph map -> anon -> callClaude -> de-anon -> save) ############\n";
echo "  (offset ~15900-22600 ham pencere)\n";
W($api,15900,7000,'doGenerate govdesi');

echo "\n\n############ 2) ANONIMLESTIRME fonksiyonu (ayri tanimli olabilir) ############\n";
R($api,'function anon',0,900,'function anon* (anonymize)');
R($api,'anonymize',-40,700,'anonymize kullanimi/tanimi');
R($api,'function deanon',0,700,'de-anonymize fonksiyonu');
R($api,'function chAnon',0,700,'chAnon (varsa)');

echo "\n\n############ 3) CASE DETAILS / prompt govdesi (answersText nasil giriyor) ############\n";
R($api,'CASE DETAILS',-200,500,'CASE DETAILS blogu');
R($api,'Keep ALL',-300,500,'Keep ALL [PLACEHOLDERS] kurali baglami');

echo "\n\n############ 4) callClaude SONRASI geri-koyma (str_replace $ph) ############\n";
R($api,'$final',-40,900,'$final = callClaude ve sonrasi (de-anon)');
R($api,'foreach($ph',0,300,'foreach ph geri-koyma');
R($api,'array_keys($ph',-60,200,'ph keys kullanimi (str_replace)');

echo "\n\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-anon.php ===\n";
