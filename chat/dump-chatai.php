<?php
/**
 * ChatHelp - dump-chatai (READ-ONLY, sir maskeli) - sohbet/uretim AI hatti (guvenilirlik + baglam).
 *  Istemci: chat gonderimi, gecmis kurulumu, 'das hat nicht geklappt' / 'Bitte warten' akisi, timeout.
 *  Sunucu: api.php chat/generate — model, curl, timeout, mesaj/gecmis, hata donusu.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-chatai.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bsk-ant[A-Za-z0-9\-_]+/','sk-ant-***',$s);
  $s=preg_replace('/\bsk-[A-Za-z0-9]{20,}/','sk-***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/([\'"]?(?:pass|password|secret|api_?key|token|bearer)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  return $s;
}
$idx=(string)@file_get_contents(__DIR__.'/index.php');
echo "== dump-chatai (index ".number_format(strlen($idx))." B) ==\n\n";

echo "=== [1] Istemci: hata/bekleme mesajlari baglami ===\n";
foreach(array('das hat nicht geklappt','nicht geklappt','Bitte warten, Ihr Dokument','wird erstellt','Verbindungsfehler') as $pat){
  $q=strpos($idx,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]: ...".mask(str_replace(array("\r","\n"),' ',substr($idx,($q-160>0?$q-160:0),320)))."...\n"; }
  else echo "  (".$pat.": yok)\n";
}

echo "\n=== [2] Istemci: chat fetch (api.php cagrisi) + gecmis kurulumu ===\n";
foreach(array('api.php?action=chat','action=chat','action=generate','messages:','history','_chHist','chatHistory','convo','msgs','role:') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($idx,$pat,$off))!==false && $n<2){
    echo "  [".$pat." @".$q."]: ...".mask(str_replace(array("\r","\n"),' ',substr($idx,($q-70>0?$q-70:0),220)))."...\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [3] Sunucu api.php: yapi ===\n";
$api=(string)@file_get_contents(__DIR__.'/api.php');
echo "  api.php: ".strlen($api)." B\n";
foreach(array('action','chat','messages','max_tokens','model','anthropic','deepseek','CURLOPT_TIMEOUT','timeout','http_code','error','history','system') as $pat){
  echo "  ".$pat.": ".substr_count($api,$pat)." kez\n";
}
echo "  -- model + timeout satirlari (maskeli) --\n";
foreach(array("'model'",'"model"','claude-','deepseek-','CURLOPT_TIMEOUT','max_tokens') as $pat){
  $off=0;$n=0;
  while(($q=strpos($api,$pat,$off))!==false && $n<3){
    echo "    [".$pat."]: ".mask(trim(str_replace(array("\r","\n"),' ',substr($api,($q-30>0?$q-30:0),120))))."\n";
    $off=$q+strlen($pat);$n++;
  }
}
echo "  -- chat action handler govdesi (ilk 900) --\n";
$q=stripos($api,"'chat'"); if($q===false)$q=stripos($api,'"chat"');
if($q!==false){ echo "    ".mask(str_replace("\n","\n    ",substr($api,($q-40>0?$q-40:0),900)))."\n"; } else echo "    (chat handler bulunamadi)\n";

echo "\n== BITTI ==\n";
