<?php
/**
 * ChatHelp — dump-sw — sw.js'nin Cloudflare tarafindan onbelleklenip
 *  onbelleklenmedigini kontrol eder (eski Service Worker'in v8'e guncellenmesini
 *  engelleyen tek sey bu olabilir) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-sw.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-sw ==\n\n";

$ch=curl_init('https://chat-help.com/chat/sw.js?_='.time());
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>20,
  CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_USERAGENT=>'ChatHelpProbe/3',
  CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]);
$raw=curl_exec($ch); $hs=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
$head=substr((string)$raw,0,$hs); $body=substr((string)$raw,$hs);

echo "=== canli sw.js header'lari ===\n";
foreach(explode("\n",$head) as $ln){ $ln=trim($ln); if($ln==='')continue;
  if(preg_match('/^(HTTP|server:|cf-|age:|cache-control:|expires:|x-cache|last-modified:|content-length:)/i',$ln)) echo "  $ln\n";
}

echo "\n=== sw.js surumu (canli) ===\n";
if(preg_match('/service worker (v\d+)/',$body,$m)) echo "  CANLI: ".$m[1]."\n";
else echo "  surum okunamadi\n";
$disk=(string)@file_get_contents(__DIR__.'/sw.js');
if(preg_match('/service worker (v\d+)/',$disk,$m2)) echo "  DISK:  ".$m2[1]."\n";

echo "\n=== teshis ===\n";
$cfCache=false;
foreach(explode("\n",$head) as $ln){ if(preg_match('/^cf-cache-status:\s*HIT/i',trim($ln))) $cfCache=true; }
if($cfCache) echo "  ⚠️ sw.js CLOUDFLARE ONBELLEGINDE (HIT) -> tarayici eski SW'yi guncelleyemez!\n";
else echo "  ✓ sw.js onbelleklenmiyor (browser guncel SW'yi gorebilir) -> sorun cihazin YEREL SW cache'i\n";

echo "\n== BITTI ==\n";
