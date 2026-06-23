<?php
/** ChatHelp — dump-aichat: api.php sohbet/analiz akışı (AI'ı Fransızca konuşturmak için) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo "[@".$p."]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "### Aksiyon yönlendirici — case satırları ###\n";
foreach(ALL($api,"case '") as $p){ echo "  ".trim(substr($api,$p,55))."\n"; }

echo "\n### Sohbet/analiz fonksiyonları ###\n";
foreach(['function doChat','function doAnalyze','function doAnalyse','function doFall','function doAsk','function doIntake','function doConversation','function doReply','function doMessage','function doProcess','function doSuggest'] as $k){
  $p=strpos($api,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($api,'function doChat',0,1100,'doChat gövdesi');
R($api,'function doAnalyze',0,1100,'doAnalyze gövdesi');
R($api,'function doFall',0,1100,'doFall gövdesi');

echo "\n### Konuşma/soru sistem promptu (Almanca talimat) ###\n";
R($api,'Stelle',-120,420,'\"Stelle Fragen\" talimatı');
R($api,'Rückfrag',-150,360,'Rückfrage talimatı');
R($api,'Du bist',-20,420,'\"Du bist…\" sistem promptu (sohbet)');
R($api,'Antworte',-40,300,'\"Antworte…\" talimatı');

echo "\n### callClaude / callDeepSeek ###\n";
foreach(['function callClaude','function callDeepSeek','function callAI','function ch_ai','function askAI'] as $k){
  $p=strpos($api,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($api,'function callClaude',0,600,'callClaude imzası');
R($api,'function callDeepSeek',0,600,'callDeepSeek imzası');

echo "\n### \$lang sohbet tarafında ###\n";
foreach(ALL($api,'$lang') as $i=>$p){ if($i>=10) break; echo "  @".$p.": ".trim(substr($api,$p,75))."\n"; }

echo "\n=== BİTTİ. SİL: rm dump-aichat.php ===\n";
