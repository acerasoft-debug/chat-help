<?php
/**
 * ChatHelp — dump-edge — SUNUCU KENDI GENEL ADRESINI DISARIDAN CAGIRIR (OKUR):
 *  [1] https://chat-help.com/chat/ yanitinin marker analizi -> kopya HANGI GUNDEN
 *  [2] Yanit header'lari -> onbellek katmaninin kimligi (Cloudflare/Sucuri/Varnish)
 *  [3] fresh.php disaridan erisilebilir mi
 *  [4] /chat klasoru: index.html golgesi var mi + dosya zamanlari
 *  [5] Sunucu kimligi (IP/docroot) — cok-sunucu suphesi icin
 * KULLANIM: pull2.php?key=...&files=dump-edge.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-edge ==\n\n";

function hit($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>25,
    CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_USERAGENT=>'Mozilla/5.0 (Linux; Android 13) ChatHelpProbe/1.0',
    CURLOPT_HTTPHEADER=>['Accept: text/html','Cache-Control: no-cache']]);
  $raw=curl_exec($ch);
  $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $hs=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);
  $ip=curl_getinfo($ch,CURLINFO_PRIMARY_IP);
  $err=curl_error($ch); curl_close($ch);
  if($raw===false) return ['code'=>0,'err'=>$err,'head'=>'','body'=>'','ip'=>$ip];
  return ['code'=>$code,'err'=>$err,'head'=>substr($raw,0,$hs),'body'=>substr($raw,$hs),'ip'=>$ip];
}
function markers($b){
  /* deploy zamani bilinen markerlarla kopyayi TARIHLE */
  $list=[
    'CH_HERO2'=>'2 gun+ once','CH_LOGO_HDR'=>'2 gun+ once','CH_NOCROSS'=>'dun sabahtan once',
    'CH_PRINTDOC_WVFIRST'=>'dun sabah','CH_PDFDIRECT'=>'dun ogleden once','CH_SENDCSS'=>'dun ogle',
    'CH_SENDEN_BIG'=>'dun aksam','CH_SONSTFIX'=>'bugun','CH_ADDR_SYNCGUARD'=>'bugun','CH_NOCACHE3'=>'az once',
  ];
  $out='';
  foreach($list as $k=>$t){ $out.='    '.(strpos($b,$k)!==false?'✓':'✗')." $k ($t)\n"; }
  return $out;
}
function fp($h){
  $sig=[];
  foreach(['server:','x-cache','cf-ray','cf-cache-status','x-sucuri','via:','x-varnish','age:','x-served-by','x-proxy-cache','x-cacheable','x-litespeed'] as $k){
    foreach(explode("\n",$h) as $ln){ if(stripos(trim($ln),$k)===0) $sig[]=trim($ln); }
  }
  return $sig?('    '.implode("\n    ",$sig)."\n"):"    (tanidik onbellek header'i yok)\n";
}

echo "=== [1] https://chat-help.com/chat/ (normal adres) ===\n";
$r=hit('https://chat-help.com/chat/');
echo "  HTTP {$r['code']} · edge-IP: {$r['ip']} · govde: ".strlen($r['body'])." B ".($r['err']?"· HATA: {$r['err']}":"")."\n";
echo "  Kopya tarihlemesi:\n".markers($r['body']);
echo "  Katman parmak izi:\n".fp($r['head']);

echo "\n=== [2] sorgulu adres (?edgeprobe) ===\n";
$r2=hit('https://chat-help.com/chat/?edgeprobe='.time());
echo "  HTTP {$r2['code']} · govde: ".strlen($r2['body'])." B\n";
echo "  Kopya tarihlemesi:\n".markers($r2['body']);

echo "\n=== [3] fresh.php disaridan ===\n";
$r3=hit('https://chat-help.com/chat/fresh.php');
echo "  HTTP {$r3['code']} · govde: ".strlen($r3['body'])." B · icerik-testi: ".(strpos($r3['body'],'ChatHelp Teşhis')!==false?'✓ dogru sayfa':'✗ FARKLI/BOS')."\n";

echo "\n=== [4] /chat klasoru golge-dosya kontrolu ===\n";
foreach(['index.html','index.htm','default.html','home.html'] as $f){
  $p=__DIR__.'/'.$f;
  echo "  $f: ".(file_exists($p)?('VAR!! ('.filesize($p).' B, '.date('d.m H:i',filemtime($p)).')'):'yok')."\n";
}
echo "  index.php mtime: ".date('d.m.Y H:i:s',@filemtime(__DIR__.'/index.php'))." (".number_format((int)@filesize(__DIR__.'/index.php'))." B)\n";
echo "  sw.js mtime: ".date('d.m.Y H:i:s',@filemtime(__DIR__.'/sw.js'))."\n";
echo "  .htaccess: ".(file_exists(__DIR__.'/.htaccess')?('VAR ('.filesize(__DIR__.'/.htaccess').' B)'):'yok')."\n";

echo "\n=== [5] sunucu kimligi ===\n";
echo "  SERVER_ADDR: ".($_SERVER['SERVER_ADDR']??'?')." · SERVER_NAME: ".($_SERVER['SERVER_NAME']??'?')."\n";
echo "  DOCUMENT_ROOT: ".($_SERVER['DOCUMENT_ROOT']??'?')."\n";
echo "  __DIR__: ".__DIR__." · host: ".gethostname()."\n";

echo "\n== BITTI ==\n";
