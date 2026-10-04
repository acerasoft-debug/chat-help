<?php
/**
 * ChatHelp — dump-postversand — send_fax API'sini DOGRULA (OKUR, gondermez).
 *  postversand.php'nin send_fax handler'ini + bekledigi POST alanlarini +
 *  dondugu yaniti gosterir. Anahtarlar (CLICKSEND/PHAXIO) MASKELENIR.
 * KULLANIM: pull2.php?key=...&files=dump-postversand.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ $s=preg_replace('/[A-Za-z0-9_\-\/+]{24,}/','***MASKED***',$s); return $s; }
$f=__DIR__.'/postversand.php';
$src=@file_get_contents($f);
if($src===false){ echo "postversand.php YOK/okunamadi\n"; exit; }
echo "== dump-postversand (".strlen($src)." B) ==\n\n";

echo '=== beklenen POST alanlari ($_POST[...]) ==='."\n";
if(preg_match_all("/\\\$_(POST|REQUEST|GET)\\[['\"]([a-zA-Z0-9_]+)['\"]\\]/",$src,$m)){
  echo "  ".implode(', ',array_values(array_unique($m[2])))."\n";
}
echo "\n=== 'send_fax' handler civari (maskeli) ===\n";
$p=strpos($src,'send_fax');
if($p!==false){ echo mask(substr($src,max(0,$p-250),1900))."\n"; }
else echo "  'send_fax' bulunamadi\n";

echo "\n=== fax servisi (ClickSend/Phaxio) endpoint + method ===\n";
foreach(['clicksend','phaxio','rest.clicksend','/fax/','fax/send','curl_init'] as $k){
  $c=substr_count(strtolower($src),strtolower($k)); if($c>0) echo "  '$k': $c\n";
}
if(preg_match('#https?://[a-z0-9./_-]*fax[a-z0-9./_-]*#i',$src,$mm)) echo "  endpoint: ".$mm[0]."\n";

echo "\n=== dondugu JSON alanlari (respond/echo json) ===\n";
if(preg_match_all("/['\"](ok|success|error|message|status|fax_id|cost|balance)['\"]/",$src,$mr)){
  echo "  ".implode(', ',array_values(array_unique($mr[1])))."\n";
}
echo "\n== BITTI ==\n";
